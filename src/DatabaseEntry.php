<?php

declare(strict_types=1);

namespace iggyvolz\yingadb;

use iggyvolz\yingadb\Condition\AlwaysTrueCondition;
use iggyvolz\yingadb\Condition\EqualToCondition;
use iggyvolz\yingadb\Transformers\Transformer;
use PhpParser\Node\Expr\BinaryOp\Equal;
use ReflectionClass;
use iggyvolz\yingadb\Drivers\IDatabase;
use iggyvolz\Initializable\Initializable;
use iggyvolz\yingadb\Condition\Condition;
use iggyvolz\ClassProperties\Identifiable;
use iggyvolz\yingadb\Attributes\TableName;
use iggyvolz\yingadb\Attributes\DBProperty;
use iggyvolz\YingaDB\Exceptions\DuplicateEntry;
use iggyvolz\virtualattributes\VirtualAttribute;
use iggyvolz\virtualattributes\ReflectionAttribute;
use iggyvolz\ClassProperties\Attributes\ReadOnlyProperty;

/**
 * A class representing a row in a database row
 * @property-read IDatabase $database The database that the row belongs to
 * @phan-forbid-undeclared-magic-properties
 */
abstract class DatabaseEntry extends Identifiable implements Initializable
{
    /**
     * @var array<string<self>,array<string,string>> Associative array of property names to column names, indexed by class name
     * @psalm-var array<class-string<self>,array<string,string>>
     */
    private static array $columnNames=[];
    /**
     * @var array<string<self>,array<string,string>> Associative array of column names to property names, indexed by class name
     * @psalm-var array<class-string<self>,array<string,string>>
     */
    private static array $reversedColumnNames=[];
    public static function init(): void
    {
        if(array_key_exists(static::class, static::$columnNames)) {
            return;
        }
        static::$columnNames[static::class] = static::getColumns();
        static::$reversedColumnNames[static::class] = array_flip(static::$columnNames[static::class]);
    }

    /**
     * @return array<string,string> Associative array of property names to column names
     */
    private static function getColumns():array
    {
        return iterator_to_array((function():\Generator{
            $refl = new ReflectionClass(static::class);

            foreach($refl->getProperties() as $property) {
                if(!empty($attributes = VirtualAttribute::getAttributes($property, DBProperty::class, ReflectionAttribute::IS_INSTANCEOF))) {
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
     */
    private array $modified = [];
    /**
     * Whether the entry has been deleted from the database
     * If true, any operation on this object other than __destruct is invalid
     */
    private bool $deleted = false;
    /**
     * The database that this instance is a member of
     */
    // <<ReadOnlyProperty>>
    protected IDatabase $database;
    protected static ?IDatabase $defaultDatabase=null;
    /**
     * Get the name of this table
     */
    private static function getTableName(): string
    {
        // todo memoize
        $refl = new ReflectionClass(static::class);
        do {
            $attributes = VirtualAttribute::getAttributes($refl, TableName::class, ReflectionAttribute::IS_INSTANCEOF);
            if(!empty($attributes)) {
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
        if(is_null($database)) {
            throw new \RuntimeException("No default database set");
        }
        // @phan-suppress-next-line PhanAccessReadOnlyMagicProperty
        $this->database = $database;
        /**
         * @var array<string, string|int>
         */
        $row = [];
        foreach(self::$columnNames[static::class] as $propertyName => $columnName) {
            $prop = $this->__get($propertyName);
            $row[$columnName] = $this->toScalar($propertyName, $prop);
        }
        $database->create(static::getTableName(), $row);
        $this->__set("database", $database);
        parent::__construct();
    }
    /**
     * Updates many rows in the database
     * Does not interface with properties
     * @param array<string,mixed> $data
     * @return void
     */
    public static function updateMany(array $data, Condition $condition, IDatabase $database = null): void
    {
        $database = $database ?? static::$defaultDatabase;
        if(is_null($database)) throw new \Exception("No default database set");
        $database->update(static::getTableName(), $condition, $data);
    }
    public static function getColumnName(string $propertyName):?string
    {
        static::init();
        return static::$columnNames[static::class][$propertyName] ?? null;
    }

    public static function getFromColumnName(string $columnName):?string
    {
        static::init();
        return static::$reversedColumnNames[static::class][$columnName] ?? null;
    }

    /**
     * @internal
     */
    public function runPreGetHook(string $property):void
    {
        if($this->deleted) {
            throw new \RuntimeException("Cannot get a property on a deleted object");
        }
    }

    /**
     * @param mixed $value
     * @internal
     */
    public function runPostSetHook(string $property, $value):void
    {
        $this->modified[$property] = static::toScalar($property, $value);
    }

    /**
     * @internal
     */
    public function runPreSetHook():void
    {
        if($this->deleted) {
            throw new \RuntimeException("Cannot set a property on a deleted object");
        }
    }

    private static function getTransformer(string $propertyName):Transformer
    {

    }

    /**
     * @param mixed $value
     * @return int|string
     */
    public function toScalar(string $propertyName, $value)
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
        if($this->deleted) {
            throw new \RuntimeException("Cannot synchronize a deleted object");
        }
        if(empty($this->modified)) {
            // No-op, the object is not modified
            return;
        }
        $condition = new EqualToCondition(static::getIdentifierName(), $this->getIdentifier());
        $this->database->update(static::getTableName(), $condition, $this->modified);
        $this->modified = [];
    }

    /**
     * Deletes many rows in the database
     *
     * @return void
     */
    public static function deleteMany(Condition $condition, IDatabase $database = null): void
    {
        $database = $database ?? static::$defaultDatabase;
        if(!$database) {
            throw new \Exception("No default database set");
        }
        $database->delete(static::getTableName(), $condition);
    }

    /**
     * Permanently deletes a row from the database
     * On success, the object becomes deleted
     *
     * @return void
     */
    public function delete(): void
    {
        if($this->deleted) {
            throw new \RuntimeException("Cannot synchronize an already deleted object");
        }
        $identifierName = static::getIdentifierName();
        $condition = new EqualToCondition(static::getIdentifierName(), $this->getIdentifier());
        $this->database->delete(static::getTableName(), $condition);
        $this->deleted = true;
    }

    /**
     * @param null|IDatabase|IDatabase[] $database Database(s) to search from, or null for the default
     * @param array<string, bool> $order Ordering of the results: column => ascending(true)/descending(false)
     */
    public static function getAll(
        Condition $condition,
        $database = null,
        ?int $limit = null,
        int $offset = 0,
        array $order = [],
        bool $prefetch = true
    ):iterable
    {
        if(is_null($database)) {
            if(self::$defaultDatabase) {
                return static::getAll($condition, self::$defaultDatabase, $limit, $offset, $order, $prefetch);
            }
        }
        if(is_array($database)) {
            foreach($database as $db) {
                yield from static::getAll($condition, $db, $limit, $offset, $order, $prefetch);
            }
        }
        foreach($database->read(static::getTableName(), $condition ?? new AlwaysTrueCondition(), $limit, $offset, $order, $prefetch) as $row) {
            $self = static::createWithoutConstructor();
            foreach($row as $key => $value) {
                $property = static::getFromColumnName($key);
                $self->__set($property, $self->fromScalar($property, $value));
            }
            yield $self;
        }
    }

    private static function createWithoutConstructor():self
    {
        /**
         * @var self
         */
        $inst = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();
        return $inst;
    }

    /**
     * @param null|IDatabase|IDatabase[] $database Database(s) to search from, or null for the default
     * @param array<string, bool> $order Ordering of the results: column => ascending(true)/descending(false)
     */
    public static function get(
        Condition $condition,
        $database = null,
        int $offset = 0,
        array $order = []
    ):?self
    {
        foreach(static::getAll($condition, $database, 1, $offset, $order, false) as $entry) {
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
        if($identifier instanceof Identifiable) {
            $identifier = $identifier->getIdentifier();
        }
        return self::get(new EqualToCondition(static::getIdentifierName(), $identifier));
    }
    public function __destruct()
    {
        if(!$this->deleted) {
            $this->sync();
        }
    }
}
(new ReadOnlyProperty)->addToProperty(DatabaseEntry::class, "database");