<?php

declare(strict_types=1);

namespace iggyvolz\yingadb;

use DateTime;
use Generator;
use ReflectionClass;
use RuntimeException;
use ReflectionProperty;
use ReflectionAttribute;
use ReflectionNamedType;
use iggyvolz\yingadb\Drivers\IDatabase;
use iggyvolz\Initializable\Initializable;
use iggyvolz\yingadb\Condition\Condition;
use iggyvolz\ClassProperties\Identifiable;
use iggyvolz\yingadb\Attributes\TableName;
use iggyvolz\yingadb\Attributes\DBProperty;
use iggyvolz\yingadb\Drivers\IBulkDatabase;
use iggyvolz\yingadb\Transformers\Transformer;
use iggyvolz\yingadb\Exceptions\DuplicateEntry;
use iggyvolz\ClassProperties\Conditions\EqualTo;
use iggyvolz\yingadb\Condition\EqualToCondition;
use iggyvolz\yingadb\Transformers\IntTransformer;
use iggyvolz\yingadb\Transformers\NullTransformer;
use iggyvolz\yingadb\Condition\AlwaysTrueCondition;
use iggyvolz\yingadb\Transformers\FloatTransformer;
use iggyvolz\yingadb\Transformers\StringTransformer;
use iggyvolz\yingadb\Transformers\DateTimeTransformer;
use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;
use iggyvolz\yingadb\Transformers\IdentifiableTransformer;
use iggyvolz\ClassProperties\Conditions\ConditionException;

/**
 * A class representing a row in a database row
 * @property-read IDatabase $database The database that the row belongs to
 * @phan-forbid-undeclared-magic-properties
 */
abstract class DatabaseEntry extends Identifiable implements Initializable
{
    /**
     * @var array<string,array<string,Transformer>>
     *     Associative array of property names to transformers, indexed by class name
     * @psalm-var array<class-string<self>,array<string,Transformer>>
     */
    private static array $transformers = [];
    /**
     * @var array<string,array<string,string>>
     *    Associative array of property names to column names, indexed by class name
     * @psalm-var array<class-string<self>,array<string,string>>
     */
    private static array $columnNames = [];
    /**
     * @var array<string,array<string,string>>
     *     Associative array of column names to property names, indexed by class name
     * @psalm-var array<class-string<self>,array<string,string>>
     */
    private static array $reversedColumnNames = [];
    public static function init(): void
    {
        if (array_key_exists(static::class, static::$columnNames)) {
            return;
        }
        static::$columnNames[static::class] = static::getColumns();
        static::$reversedColumnNames[static::class] = array_flip(static::$columnNames[static::class]);
        static::$transformers[static::class] = static::getTransformers();
        parent::init();
    }

    /**
     * @return array<string,Transformer>
     */
    private static function getTransformers(): array
    {
        return iterator_to_array((
            /**
             * @return \Generator<string,Transformer>
             */
            function (): \Generator {
                $refl = new ReflectionClass(static::class);

                foreach ($refl->getProperties() as $property) {
                    if (
                        !empty($attributes = $property->getAttributes(
                            DBProperty::class,
                            ReflectionAttribute::IS_INSTANCEOF
                        ))
                    ) {
                        if (
                            !empty($attributes = $property->getAttributes(
                                Transformer::class,
                                ReflectionAttribute::IS_INSTANCEOF
                            ))
                        ) {
                            /** @var Transformer */
                            $attr = $attributes[0]->newInstance();
                            yield $property->getName() => $attr;
                        } else {
                            yield $property->getName() => static::getTransformerFor($property);
                        }
                    }
                }
            })());
    }

    private static function getTransformerFor(ReflectionProperty $property, bool $alreadyWrapped = false): Transformer
    {
        // Get transformer type based off property type
        $type = $property->getType();
        $pname = $property->getName();
        if (!$type) {
            throw new \LogicException(
                "Could not autodetect transformer for untyped property $pname"
            );
        }
        if (!$type instanceof ReflectionNamedType) {
            throw new \LogicException(
                "Could not autodetect transformer for union typed property $pname"
            );
        }
        if ($type->allowsNull() && !$alreadyWrapped) {
            return new NullTransformer(static::getTransformerFor($property, true));
        }
        if ($type->isBuiltin()) {
            switch ($type->getName()) {
                case "int":
                    return new IntTransformer();
                case "string":
                    return new StringTransformer();
                case "float":
                    return new FloatTransformer();
                default:
                    throw new \LogicException("Could not get transformer type for " . $type->getName());
            }
        }
        $tname = $type->getName();
        if (is_subclass_of($tname, Identifiable::class)) {
            $otherClass = new ReflectionClass($tname);
            $otherIdentifier = $otherClass->getProperty($tname::getIdentifierName());
            $identifiableTransformer = static::getTransformerFor($otherIdentifier);
            return new IdentifiableTransformer($identifiableTransformer, $tname);
        }
        switch ($type->getName()) {
            case DateTime::class:
                return new DateTimeTransformer();
            default:
                throw new \LogicException("Could not get transformer type for " . $type->getName());
        }
    }

    /**
     * @return array<string,string> Associative array of property names to column names
     */
    private static function getColumns(): array
    {
        return iterator_to_array((/**
         * @return \Generator<string,string>
         */function (): \Generator {
            $refl = new ReflectionClass(static::class);

    foreach ($refl->getProperties() as $property) {
        if (
            !empty($attributes = $property->getAttributes(
                DBProperty::class,
                ReflectionAttribute::IS_INSTANCEOF
            ))
        ) {
            /** @var TableName */
            $attr = $attributes[0]->newInstance();
            $columnName = $attr->columnName ?? $property->getName();
            yield $property->getName() => $columnName;
        }
    }
})());
    }

    /**
     * Properties which are out of sync with the database
     * @var array<string, string|int|null|float>
     */
    private array $modified = [];

    /**
     * Identifier as per the database
     * Will only be updated on a synchronize call
     */
    private int|string $identifier;
    /**
     * Whether the entry has been deleted from the database
     * If true, any operation on this object other than __destruct is invalid
     */
    private bool $deleted = false;
    /**
     * The database that this instance is a member of
     */
    <<ReadOnlyProperty>>
    protected IDatabase $database;
    protected static ?IDatabase $defaultDatabase = null;
    public static function setDefaultDatabase(?IDatabase $defaultDatabase): void
    {
        static::init();
        self::$defaultDatabase = $defaultDatabase;
    }
    public static function getDefaultDatabase(): ?IDatabase
    {
        static::init();
        return self::$defaultDatabase;
    }
    /**
     * Get the name of this table
     */
    public static function getTableName(): string
    {
        static::init();
        // todo memoize
        $refl = new ReflectionClass(static::class);
        do {
            $attributes = $refl->getAttributes(TableName::class, ReflectionAttribute::IS_INSTANCEOF);
            if (!empty($attributes)) {
                /** @var TableName */
                $attr = $attributes[0]->newInstance();
                return $attr->tableName;
            }
        } while ($refl = $refl->getParentClass());
        return hash("sha256", static::class);
    }
    /**
     * @throws DuplicateEntry A row already exists with a duplicate unique/primary key
     */
    public function __construct(?IDatabase $database)
    {
        static::init();
        $database = $database ?? self::$defaultDatabase;
        if (is_null($database)) {
            throw new \RuntimeException("No default database set");
        }
        // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
        $this->database = $database;
        /**
         * @var array<string, string|int>
         */
        $row = [];
        foreach (self::$columnNames[static::class] as $propertyName => $columnName) {
            $row[$columnName] = static::toScalar($propertyName, $this->__get($propertyName));
        }
        $database->create(static::getTableName(), $row);
        $this->identifier = $this->getIdentifier();
        parent::__construct();
    }
    /**
     * Updates many rows in the database
     * Does not interface with properties
     * @param array<string,mixed> $data
     * @return void
     */
    public static function updateMany(array $data, array $condition, IDatabase $database = null): void
    {
        static::init();
        $database = $database ?? static::$defaultDatabase;
        if (is_null($database)) {
            throw new \RuntimeException("No default database set");
        }
        $dataTransformed = static::transformKeysToScalar($data, "updating");
        if($database instanceof IBulkDatabase) {
            if($database->bulkUpdate(static::getTableName(), self::transformKeys($condition), self::$transformers[static::class], $dataTransformed)) {
                return;
            }
        }
        foreach(static::getAll($condition, $database) as $item) {
            foreach($data as $key => $value) {
                $item->__set($key, $value);
            }
        }
    }
    public static function getColumnName(string $propertyName): ?string
    {
        static::init();
        return static::$columnNames[static::class][$propertyName] ?? null;
    }

    public static function getFromColumnName(string $columnName): ?string
    {
        static::init();
        return static::$reversedColumnNames[static::class][$columnName] ?? null;
    }

    /**
     * @internal
     */
    public function runPreGetHook(string $property): void
    {
        if ($this->deleted) {
            throw new \RuntimeException("Cannot get a property on a deleted object");
        }
    }

    /**
     * @param mixed $value
     * @internal
     */
    public function runPostSetHook(string $property, $value): void
    {
        $this->modified[$property] = static::toScalar($property, $value);
    }

    /**
     * @internal
     */
    public function runPreSetHook(): void
    {
        if ($this->deleted) {
            throw new \RuntimeException("Cannot set a property on a deleted object");
        }
    }

    private static function getTransformer(string $propertyName): Transformer
    {
        static::init();
        $transformer = self::$transformers[static::class][$propertyName] ?? null;
        if (is_null($transformer)) {
            throw new \InvalidArgumentException();
        }
        return $transformer;
    }

    /**
     * @param mixed $value
     * @return int|float|string|null
     */
    public static function toScalar(string $propertyName, $value)
    {
        return static::getTransformer($propertyName)->toScalar($value);
    }

    /**
     * @param int|float|string|null $value
     * @return mixed
     */
    public static function fromScalar(string $propertyName, $value)
    {
        return static::getTransformer($propertyName)->fromScalar($value);
    }

    /**
     * Synchronizes the object with the database
     *
     * @return void
     */
    public function sync(): void
    {
        if ($this->deleted) {
            throw new \RuntimeException("Cannot synchronize a deleted object");
        }
        if (empty($this->modified)) {
            // No-op, the object is not modified
            return;
        }
        $this->database->update(static::getTableName(), $this->identifier, self::transformKeysToScalar($this->modified, "update"));
        $this->identifier = $this->getIdentifier();
        $this->modified = [];
    }

    /**
     * Deletes many rows in the database
     *
     * @psalm-var array<string,T> $condition
     * @return void
     */
    public static function deleteMany(array $condition, IDatabase $database = null): void
    {
        static::init();
        $database = $database ?? static::$defaultDatabase;
        if (!$database) {
            throw new \RuntimeException("No default database set");
        }
        if($database instanceof IBulkDatabase) {
            if($database->bulkDelete(static::getTableName(), self::transformKeys($condition), self::$transformers[static::class])) {
                return;
            }
        }
        foreach(static::getAll($condition, $database) as $item) {
            $item->delete();
        }
    }

    /**
     * Permanently deletes a row from the database
     * On success, the object becomes deleted
     *
     * @return void
     */
    public function delete(): void
    {
        if ($this->deleted) {
            throw new \RuntimeException("Cannot delete an already deleted object");
        }
        $this->database->delete(static::getTableName(), $this->getIdentifier());
        $this->deleted = true;
    }

    /**
     * @param null|IDatabase|IDatabase[] $database Database(s) to search from, or null for the default
     * @param array<string, bool> $order Ordering of the results: column => ascending(true)/descending(false)
     * @return iterable<static>
     * @phan-return iterable<DatabaseEntry>
     *   -> https://github.com/phan/phan/issues/2147
     */
    public static function getAll(
        array $condition = [],
        $database = null,
        ?int $limit = null,
        int $offset = 0,
        array $order = [],
        bool $prefetch = true
    ): iterable {
        static::init();
        if (is_null($database)) {
            if (self::$defaultDatabase) {
                yield from static::getAll($condition, self::$defaultDatabase, $limit, $offset, $order, $prefetch);
            } else {
                throw new \RuntimeException("No default database set");
            }
            return;
        }
        if (is_array($database)) {
            if (empty($order)) {
                foreach ($database as $db) {
                    yield from static::getAll($condition, $db, $limit, $offset, $order, $prefetch);
                }
            } else {
                $data = iterator_to_array(
                    (
                        /** @return \Generator<static> */
                        function () use ($prefetch, $order, $offset, $limit, $condition, $database): Generator {
                            foreach ($database as $db) {
                                yield from static::getAll($condition, $db, $limit, $offset, $order, $prefetch);
                            }
                        })(),
                    false
                );
                usort($data, function (self $s1, self $s2) use ($order): int {
                    foreach ($order as $prop => $asc) {
                        $val1 = static::toScalar($prop, $s1->__get($prop));
                        $val2 = static::toScalar($prop, $s2->__get($prop));
                        $comp = $val1 <=> $val2;
                        if ($comp === 0) {
                            continue;
                        }
                        if (!$asc) {
                            $comp *= -1;
                        }
                        return $comp;
                    }
                    // Undefined behaviour
                    // @codeCoverageIgnoreStart
                    return 0;
                    // @codeCoverageIgnoreEnd
                });
                $data = array_values($data);
                yield from $data;
            }
            return;
        }
        // Translate order into column names
        $newOrder = self::transformKeys($order, "sorting");
        // Translate conditions into column names
        $newCondition = self::transformKeys($condition, "condition");
        foreach (
            $database->read(
                static::getTableName(),
                $newCondition,
                $limit,
                $offset,
                $newOrder,
                $prefetch
            ) as $row
        ) {
            try {
                $self = static::createWithoutConstructor();
                // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
                $self->database = $database;
                foreach ($row as $key => $value) {
                    $property = static::getFromColumnName($key);
                    if (!is_null($property)) {
                        $self->__set($property, $self->fromScalar($property, $value));
                    }
                }
                $self->identifier = $self->getIdentifier();
                yield $self;
            } catch(ConditionException $_) {
                // Do not yield this row
            }
        }
    }

    /**
     * @psalm-var array<string,T> $arr
     * @psalm-return array<string,T>
     */
    private static function transformKeys(array $arr, string $purpose):array
    {
        return iterator_to_array((function() use($arr, $purpose){
            foreach($arr as $prop => $value) {
                $col = static::getColumnName($prop);
                if (is_null($col)) {
                    throw new RuntimeException("Invalid property name $prop for $purpose");
                }
                yield $col => $value;
            }
        })());
    }

    /**
     * @var array<string,mixed> $arr
     * @return array<string,int|float|string|null>
     */
    private static function transformKeysToScalar(array $arr, string $purpose):array
    {
        return iterator_to_array((function() use($arr, $purpose){
            foreach($arr as $prop => $value) {
                $col = static::getColumnName($prop);
                if (is_null($col)) {
                    throw new RuntimeException("Invalid property name $prop for $purpose");
                }
                yield $col => static::toScalar($prop, $value);
            }
        })());
    }

    /**
     * @return static
     */
    private static function createWithoutConstructor(): self
    {
        $inst = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        return $inst;
    }

    /**
     * @param null|IDatabase|IDatabase[] $database Database(s) to search from, or null for the default
     * @param array<string, bool> $order Ordering of the results: column => ascending(true)/descending(false)
     * @return ?static
     */
    public static function get(
        array $condition,
        $database = null,
        int $offset = 0,
        array $order = []
    ): ?self {
        static::init();
        foreach (static::getAll($condition, $database, 1, $offset, $order, false) as $entry) {
            return $entry;
        }
        return null;
    }
    /**
     * Get an object from an identifier
     * @param int|string|Identifiable $identifier
     * @override
     * @return static|null
     * @phan-suppress PhanParamSignatureRealMismatchReturnType
     *   -> https://github.com/phan/phan/issues/2836
     */
    public static function getFromIdentifier($identifier): ?self
    {
        static::init();
        if ($identifier instanceof Identifiable) {
            $identifier = $identifier->getIdentifier();
        }
        return static::get([static::getIdentifierName() => new EqualTo($identifier)]);
    }
    public function __destruct()
    {
        if (!$this->deleted) {
            $this->sync();
        }
    }
}
