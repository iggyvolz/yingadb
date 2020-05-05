<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable;

use Exception;
use Generator;
use IteratorIterator;
use JsonSerializable;
use RuntimeException;
use DateTimeInterface;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperties;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Scalar;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\Condition;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Property;
use GCCISWebProjects\Utilities\Iterators\RewindableIteratorFactory;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\AllCondition;
use GCCISWebProjects\Utilities\DatabaseTable\Exceptions\StateException;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\AlwaysTrueCondition;
use GCCISWebProjects\Utilities\DatabaseTable\Exceptions\MariaDBException\DupEntry;
use GCCISWebProjects\Utilities\DatabaseTable\Exceptions\MariaDBException\NoSuchTable;
use GCCISWebProjects\Utilities\DatabaseTable\Exceptions\MariaDBException\UnknownTable;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Identifiable as IdentifiableProperty;

/**
 * A class representing a row in a database table
 * @property bool $IsActive Whether or not the row is active @database-column @default true
 * @property-read string $Database The database that the row belongs to
 * @phan-forbid-undeclared-magic-properties
 */
abstract class DatabaseTable extends ClassProperties implements JsonSerializable
{
    /**
     * Note on states: If a method throws an exception (or, in the case of fill*, returns false),
     * its state remains unchanged
     */
    /**
     * This object has just been created and had properties modified; any properties set do not reflect the database
     * The following methods will move the object to STATE_INITIALIZED: Get, Set
     * The following methods will move the object to STATE_FRESH: Insert, Fill
     * The following methods are invalid in this state and will throw a StateException: Update, Delete
     * The following methods will move the object to STATE_DISCARDED: Discard
     */
    public const STATE_INITIALIZED = 0;
    /**
     * This object has just been synchronized the database and does reflect the database
     * The following methods will move the object to STATE_FRESH: Get, Update
     * The following methods are invalid in this state and will throw a StateException: Insert, Fill
     * The following methods will move the object to STATE_MODIFIED: Set
     * The following methods will move the object to STATE_DISCARDED: Discard, Delete
     */
    public const STATE_FRESH = 1;
    /**
     * This object has been marked as discarded and any further attempts to interact with it must result in an error
     * The following methods will move the object to STATE_DISCARDED: Discard
     * The following methods are invalid in this state and will throw a StateException: Get, Insert, Update, Fill, Set, Delete
     */
    public const STATE_DISCARDED = 2;
    /**
     * This object has been modified from a row which was earlier synchronized with the database
     * The following methods are invalid in this state and will throw a StateException: Insert, Fill, Get, Delete
     * The following methods will move the object to STATE_FRESH: Update
     * The following methods will move the object to STATE_DISCARDED: Discard
     * The following methods will move the object to STATE_MODIFIED: Set
     */
    public const STATE_MODIFIED = 3;

    /**
     * This object has been pulled from the database, but only contains its primary key
     * The following methods will move the object to STATE_PARTIAL_FRESH: Update, Get (identifier)
     * The following methods will fetch the object and move it to STATE_FRESH: Get (non-identifier), Fill
     * The following methods are invalid in this state and will throw a StateException: Insert
     * The following methods will fetch the object and move it to to STATE_MODIFIED: Set
     * The following methods will move the object to STATE_DISCARDED: Discard, Delete
     */
    public const STATE_PARTIAL_FRESH = 4;

    /**
     * This object is a dummy object and should not be used for any database operations
     * The following methods will move the object to STATE_DUMMY: Get, Shutdown
     * The following methods are invalid in this state and will throw a StateException: Set, Update, Fill, Insert, Discard
     */
    public const STATE_DUMMY = 5;
    /**
     * The current state of the object, see the STATE_ constants
     *
     * @var int
     */
    private $state = self::STATE_INITIALIZED;
    public function getCurrentState(): int
    {
        return $this->state;
    }
    public function setCurrentState(int $state): void
    {
        $this->state = $state;
    }
    /**
     * The connection to the database
     *
     * @var IDatabaseDriver|null
     */
    protected static $driver = null;
    /**
     *Use the DatabaseTable manually unless you *need* access to the driver
     *
     * @return IDatabaseDriver
     */
    public static function getDatabaseDriver(): IDatabaseDriver
    {
        if (is_null(self::$driver)) {
            throw new RuntimeException("Driver not set");
        }
        return self::$driver;
    }
    /**
     * List of databases
     *
     * @var string[]
     */
    private static $databaseList = [];
    /**
     * Set the database driver for DatabaseTable
     *
     * @param IDatabaseDriver $driver Driver to set
     * @param string[] $databaseList Databases that are available on the driver
     * @return void
     */
    public static function setDatabaseDriver(IDatabaseDriver $driver, array $databaseList): void
    {
        self::$driver = $driver;
        self::$databaseList = $databaseList;
    }
    /**
     * Get the list of databases known to DatabaseTable
     *
     * @return string[] $databaseList Databases that are available on the driver
     */
    public static function getDatabaseList(): array
    {
        return self::$databaseList;
    }
    /**
     * The default database
     *
     * @var string|null
     */
    private static $defaultDatabase = null;
    public static function setDefaultDatabase(string $defaultDatabase): void
    {
        self::$defaultDatabase = $defaultDatabase;
    }
    public static function getDefaultDatabase(): string
    {
        if (is_null(self::$defaultDatabase)) {
            throw new RuntimeException("Default database not set");
        }
        return self::$defaultDatabase;
    }
    /**
     * The database of this object
     *
     * @var string|null
     */
    private $database = null;
    /**
     * Get the database that this row belongs to
     *
     * @return string
     */
    protected function getDatabase(): string
    {
        return $this->database ?? self::getDefaultDatabase();
    }
    public static function getTableName(): string
    {
        $refl = new \ReflectionClass(static::class);
        do {
            $doc = $refl->getDocComment();
            $out = [];
            if ($doc && preg_match('/@database-table-name ([^ \r\n]*)/', $doc, $out)) {
                return $out[1];
            }
        } while ($refl = $refl->getParentClass());
        return str_replace("\\", "_", static::class);
    }
    /**
     * Inserts a row into the database
     * On success, the object enters STATE_FRESH; on failure, the object retains its state
     *
     * @throws StateException This object is not in STATE_INITIALIZED
     * @throws DupEntry A row already exists with a duplicate unique/primary key
     * @return void
     */
    public function insert(): void
    {
        if ($this->state === self::STATE_INITIALIZED) {
            $this->setCurrentState(self::STATE_FRESH);
            $row = [];
            $postActions = [];
            foreach (ClassProperty::getProperties(static::class) as $prop) {
                if ($prop instanceof Property) {
                    $pname = $prop->Name;
                    try {
                        $postAction = $prop->Write($this->__get($pname), $row, $this->getDatabase());
                        if ($postAction) {
                            $postActions[] = $postAction;
                        }
                    } catch (\InvalidArgumentException $e) {
                        // this is okay
                    }
                }
            }
            $this->setCurrentState(self::STATE_FRESH);
            $pkeyname = $this->getPrimaryKey()->Name;
            if ($this->getPrimaryKey()->hasTag("auto-increment") && !$this->__isset($pkeyname)) {
                $this->__set($pkeyname, self::getDatabaseDriver()->create($this->Database, static::class, $row));
                foreach ($postActions as $postAction) {
                    $this->setCurrentState(self::STATE_FRESH);
                    $postAction($this->__get($pkeyname));
                }
            } else {
                self::getDatabaseDriver()->create($this->getDatabase(), static::class, $row);
            }
            $this->setCurrentState(self::STATE_FRESH);
        } else {
            throw new StateException("Attempt to insert a row which was in state " . $this->state);
        }
    }
    /**
     * Updates many rows in the database
     * Does not interface with properties
     * @param array<string,int|string|bool|Identifiable|DateTimeInterface> $data
     * @return void
     */
    public static function updateMany(array $data, Condition $condition, string $database = null): void
    {
        $data = array_map(
            /**
             * @param int|string|bool|Identifiable|DateTimeInterface $val
             * @return int|string
             */
            function ($val) {
                return Condition::transform($val);
            },
            $data
        );
        self::getDatabaseDriver()->Update($database ?? self::getDefaultDatabase(), static::class, $condition, $data);
    }

    /**
     * Updates a row in the database
     * On success, the object enters STATE_FRESH; on failure, the object retains its state
     *
     * @throws StateException The object is not in the correct state
     * @return void
     */
    public function update(): void
    {
        if ($this->state === self::STATE_MODIFIED) {
            $row = [];
            $this->setCurrentState(self::STATE_FRESH); // allow us to get properties
            foreach (ClassProperty::getProperties(static::class) as $prop) {
                if ($prop instanceof Property) {
                    $pname = $prop->Name;
                    $prop->Write($this->__get($pname), $row, $this->Database);
                }
            }
            $condition = new AllCondition();
            $pkey = $this->getPrimaryKey();
            $pkey->Check($this->{$pkey->Name}, $condition, $this->Database);
            static::updateMany($row, $condition, $this->Database);
        } elseif ($this->state === self::STATE_FRESH || $this->state === self::STATE_PARTIAL_FRESH) {
            // No-op, we didn't change anything
        } else {
            throw new StateException("Attempt to update a row which was in state " . $this->state);
        }
    }

    /**
     * Deletes many rows in the database
     * This is unsafe - prefer using ::update with IsActive set to false
     *
     * @return void
     */
    public function deleteMany(Condition $condition, string $database = null): void
    {
        self::getDatabaseDriver()->delete($database ?? self::getDefaultDatabase(), static::class, $condition);
    }

    /**
     * Permanently deletes a row from the database
     * On success, the object enters STATE_DISCARDED; on failure, the object retains its state
     * This is unsafe - prefer using ::update with IsActive set to false
     *
     * @throws StateException The object is not in the correct state
     * @return void
     */
    public function delete(): void
    {
        if ($this->state === self::STATE_FRESH || $this->state === self::STATE_PARTIAL_FRESH) {
            $condition = new AllCondition();
            $pkey = $this->getPrimaryKey();
            $pkey->Check($this->{$pkey->Name}, $condition, $this->Database);
            static::deleteMany($condition, $this->Database);
            $this->discard();
        } else {
            throw new StateException("Attempt to delete a row which was in state " . $this->state);
        }
    }
    /**
     * Marks the object as discarded and prevents further use
     * The object enters STATE_DISCARDED, and no further actions are valid at this point
     *
     * @return void
     */
    public function discard(): void
    {
        $this->setCurrentState(self::STATE_DISCARDED);
    }
    /**
     * Attempts to fill the object from the database
     * On success, the object enters STATE_FRESH; on failure (exception or return false), the object retains its state
     *
     * @throws StateException This object is not in STATE_INITIALIZED
     * @return bool True if a matching object was found in the database, false otherwise
     */
    public function fill(): bool
    {
        return $this->fillFromDatabase(self::getDefaultDatabase());
    }

    /**
     * Attempts to fill the object from a given database
     * On success, the object enters STATE_FRESH; on failure (exception or return false), the object retains its state
     *
     * @throws StateException This object is not in STATE_INITIALIZED
     * @param string $database The database to select from
     * @return bool True if a matching object was found in the database, false otherwise
     */
    public function fillFromDatabase(string $database): bool
    {
        if ($this->getCurrentState() === self::STATE_FRESH) {
            return true;
        }
        $condition = new AllCondition();
        foreach (ClassProperty::getProperties(static::class) as $prop) {
            if ($prop instanceof Property) {
                $pname = $prop->Name;
                 // Ignore the state of the object
                if ($this->realIsset($pname, false, true)) {
                    $prop->Check($this->realGet($pname, false, true), $condition, $this->Database);
                }
            }
        }
        $rows = static::getDatabaseDriver()->read($database, static::class, $condition, 1, 0);
        foreach ($rows as $row) {
            foreach (ClassProperty::getProperties(static::class) as $prop) {
                if ($prop instanceof Property) {
                    $pname = $prop->Name;
                    $this->realSet($pname, $prop->read($row, $this->getDatabase()), true);
                }
            }
            $this->setCurrentState(self::STATE_FRESH);
            return true;
        }
        return false;
    }

    /**
     * Attempts to fill the object from any database
     * On success, the object enters STATE_FRESH; on failure (exception or return false), the object retains its state
     *
     * @throws StateException This object is not in STATE_INITIALIZED
     * @return bool True if a matching object was found in the database, false otherwise
     */
    public function fillFromAllDatabases(): bool
    {
        foreach (self::$databaseList as $database) {
            if ($this->fillFromDatabase($database)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Attempts to retrieve an object from the database
     *
     * @param Condition $where The condition to check the object for
     * @param string[] $order What column(s) to sort by
     * @param bool[] $asc Whether to sort ascending or descending
     * @return static|null An object of this class (with STATE_FRESH) if a matching object was found in the database,
     *  false otherwise
     */
    public static function get(
        Condition $where,
        int $offset = 0,
        array $order = [],
        array $asc = []
    ): ?self {
        return static::getFromDatabase($where, self::getDefaultDatabase(), $offset, $order, $asc);
    }

    /**
     * Attempts to retrieve an object from a given database
     *
     * @param Condition $where The condition to check the object for
     * @param string $database The database to search in
     * @param string[] $order What column(s) to sort by
     * @param bool[] $asc Whether to sort ascending or descending
     * @return static|null An object of this class (with STATE_FRESH) if a matching object was found in the database,
     *  false otherwise
     */
    public static function getFromDatabase(
        Condition $where,
        string $database,
        int $offset = 0,
        array $order = [],
        array $asc = []
    ): ?self {
        foreach (static::getAllFromDatabase($database, $where, 1, $offset, $order, $asc, false) as $row) {
            return $row;
        }
        return null;
    }

    /**
     * Attempts to retrieve an object from any database
     *
     * @param Condition $where The condition to check the object for
     * @param string[] $order What column(s) to sort by
     * @param bool[] $asc Whether to sort ascending or descending
     * @return static|null An object of this class (with STATE_FRESH) if a matching object was found in the database,
     *  false otherwise
     */
    public static function getFromAllDatabases(
        Condition $where,
        int $offset = 0,
        array $order = [],
        array $asc = []
    ): ?self {
        foreach (static::getAllFromAllDatabases($where, 1, $offset, $order, $asc, false) as $row) {
            return $row;
        }
        return null;
    }
    /**
     * Retrieves all objects matching a condition from the database
     *
     * @param null|Condition $where The condition to check the object for
     * @param int|null $limit The limit of the number of objects to retrieve
     * @param string[] $order What column(s) to sort by
     * @param bool[] $asc Whether to sort ascending or descending
     * @return RewindableIteratorFactory<static> An iterator over all objects matching this condition (in STATE_FRESH)
     */
    public static function getAll(
        Condition $where = null,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        array $asc = [],
        bool $prefetch = true
    ): RewindableIteratorFactory {
        return static::getAllFromDatabase(self::getDefaultDatabase(), $where ?? new AlwaysTrueCondition(), $limit, $offset, $order, $asc, $prefetch);
    }
    /**
     * Retrieves all objects matching a condition from a given database
     *
     * @param string $database The database to search in
     * @param null|Condition $where The condition to check the object for
     * @param int|null $limit The limit of the number of objects to retrieve
     * @param string[] $order What column(s) to sort by
     * @param bool[] $asc Whether to sort ascending or descending
     * @return RewindableIteratorFactory An iterator over all objects matching this condition (in STATE_FRESH)
     */
    public static function getAllFromDatabase(
        string $database,
        Condition $where = null,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        array $asc = [],
        bool $prefetch = true,
        bool $ignoreunknowntable = false
    ): RewindableIteratorFactory {
        return new RewindableIteratorFactory((function () use ($where, $database, $limit, $offset, $order, $asc, $prefetch, $ignoreunknowntable): Generator {
            try {
                foreach (static::getDatabaseDriver()->read($database, static::class, $where ?? new AlwaysTrueCondition(), $limit, $offset, $order, $asc, $prefetch) as $row) {
                    $item = new static();

                    foreach (ClassProperty::getProperties(static::class) as $prop) {
                        if ($prop instanceof Property) {
                            $pname = $prop->Name;
                            $item->realSet($pname, $prop->read($row, $database), true);
                        }
                    }
                    $item->state = self::STATE_FRESH;
                    yield $item;
                }
            } catch (NoSuchTable $e) {
            } catch (UnknownTable $e) {
                if ($ignoreunknowntable) {
                    return;
                } else {
                    throw $e;
                }
            }
        })());
    }

    /**
     * Retrieves all objects matching a condition from any database
     *
     * @param null|Condition $where The condition to check the object for
     * @param int|null $limit The limit of the number of objects to retrieve
     * @param string[] $order What column(s) to sort by
     * @param bool[] $asc Whether to sort ascending or descending
     * @return RewindableIteratorFactory An iterator over all objects matching this condition (in STATE_FRESH)
     */
    public static function getAllFromAllDatabases(
        Condition $where = null,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        array $asc = [],
        bool $prefetch = false
    ): RewindableIteratorFactory {
        $it = new \AppendIterator();
        foreach (self::$databaseList as $database) {
            $it->append(new IteratorIterator(static::getAllFromDatabase($database, $where ?? new AlwaysTrueCondition(), $limit, $offset, $order, $asc, $prefetch, true)));
        }
        return new RewindableIteratorFactory($it);
    }

    /**
     * @phan-var array<string,array<int,ClassProperty>> Columns for a class
     */
    private static $columns = [];
    /**
     * Get columns for this class
     * @return Property[] Array of properties
     */
    public static function getColumns(): array
    {
        if (!array_key_exists(static::class, self::$columns)) {
            self::$columns[static::class] = array_values(array_filter(
                ClassProperty::getProperties(static::class),
                function (ClassProperty $prop): bool {
                    return $prop instanceof IdentifiableProperty || $prop instanceof Scalar;
                }
            ));
        }
        return self::$columns[static::class];
    }
    /**
     * Get a column for this class
     * @return null|Property Property that matches this name
     */
    public static function getColumn(string $name): ?Property
    {
        foreach (self::getColumns() as $column) {
            if ($column->Name === $name) {
                return $column;
            }
        }
        return null;
    }

    /**
     * Get the primary key for this class
     *
     * @return Property The primary key for this class
     */
    public static function getPrimaryKey(): Property
    {
        $property = self::getColumn(Identifiable::getIdentifierName(static::class));
        if (is_null($property)) {
            throw new Exception("Invalid identifier " . Identifiable::getIdentifierName(static::class) . " found on class " . static::class);
        }
        return $property;
    }

    /**
     * Get the unique keys for this class
     *
     * @return Property[][] Unique keys for this class
     */
    public static function getUniqueKeys(): array
    {
        return iterator_to_array((function (): Generator {
            foreach (static::getColumns() as $column) {
                if ($column->hasTag("unique")) {
                    yield [$column];
                }
                if ($column->hasTag("unique-with")) {
                    yield iterator_to_array((function () use ($column): Generator {
                        yield $column;
                        /** @psalm-suppress PossiblyNullArgument */
                        foreach (json_decode($column->getTag("unique-with")) as $otherColumn) {
                            yield static::getColumn($otherColumn);
                        }
                    })());
                }
            }
        })());
    }


    /**
     * Get the default values for this class
     *
     * @return string[] Default values for this class
     */
    public static function getDefaults(): array
    {
        return iterator_to_array((function (): Generator {
            foreach (static::getColumns() as $column) {
                if ($column->hasTag("default")) {
                    /** @psalm-suppress PossiblyNullArgument */
                    yield $column->Name => json_decode($column->getTag("default"));
                }
            }
        })());
    }
    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(bool $throwOnNotFound = true): array
    {
        return iterator_to_array((function () use ($throwOnNotFound): Generator {
            foreach (static::getColumns() as $prop) {
                try {
                    if ($prop->CanRead) {
                        $value = $this->__get($prop->Name);
                        if (is_iterable($value)) {
                            yield $prop->Name => iterator_to_array((function () use ($value): Generator {
                                if (!is_array($value)) {
                                    $value = iterator_to_array($value);
                                }
                                foreach ($value as $item) {
                                    if ($item instanceof Identifiable) {
                                        yield $item->getIdentifier();
                                    } else {
                                        yield $item;
                                    }
                                }
                            })());
                        } elseif ($value instanceof Identifiable) {
                            yield $prop->Name => $value->getIdentifier();
                        } else {
                            yield $prop->Name => $value;
                        }
                    }
                } catch (\InvalidArgumentException $_) {
                    // Try to get default value
                    if ($throwOnNotFound && !$prop->hasTag("auto-increment")) {
                        throw new \InvalidArgumentException("Unset property {$prop->Name}");
                    }
                }
            }
        })());
    }
    public function __get(string $name)
    {
        return $this->realGet($name);
    }
    protected function realGet(string $name, bool $fillDefault = true, bool $ignoreState = false)
    {
        if ($name !== "Database" && !$ignoreState) {
            switch ($this->state) {
                case self::STATE_PARTIAL_FRESH:
                    if ($name === Identifiable::getIdentifierName(static::class)) {
                        break;
                    }
                    if (!$this->fill()) {
                        throw new StateException("Could not get property after failed fill");
                    }
                    break;
                case self::STATE_INITIALIZED:
                    break;
                case self::STATE_MODIFIED:
                    throw new StateException("Cannot get property of modified object");
                case self::STATE_DISCARDED:
                    throw new StateException("Cannot get property of discarded object");
            }
        }
        $ret = parent::realGet($name, $fillDefault);
        if ($ret instanceof RewindableIteratorFactory) {
            return $ret->getIterator();
        } else {
            return $ret;
        }
    }
    private function realIsset(string $name, bool $fillDefault = true, bool $ignoreState = false): bool
    {
        try {
            $this->realGet($name, $fillDefault, $ignoreState);
        } catch (\Exception $e) {
            return false;
        }
        return true;
    }
    public function __isset(string $name): bool
    {
        return $this->realIsset($name);
    }
    public function __set(string $name, $value): void
    {
        if ($this->state === self::STATE_PARTIAL_FRESH) {
            $this->fill();
        }
        if ($this->state === self::STATE_DISCARDED) {
            throw new StateException("Cannot set property of discarded object");
        }
        parent::__set($name, $value);
        if ($name !== "Database" && $this->state === self::STATE_FRESH) {
            // This is no longer fresh
            $this->setCurrentState(self::STATE_MODIFIED);
        }
    }
    public function insertUpdate(): void
    {
        try {
            $this->insert();
        } catch (DupEntry $e) {
            /** @psalm-var string */
            $keyName = $e[1];
            if ($keyName === "PRIMARY") {
                // ID already exists
                $this->setCurrentState(self::STATE_MODIFIED);
                $this->update();
            } else {
                // Need to set the identifier to match the missing column
                $matchingRow = new static();
                foreach (explode("_", $keyName) as $column) {
                    $matchingRow->__set($column, $this->__get($column));
                }
                $matchingRow->fill();
                $identifier = $matchingRow->getIdentifier();
                // Set the identifier of this to match the conflicting row
                $this->__set(self::getIdentifierName(static::class), $identifier);
                $this->setCurrentState(self::STATE_MODIFIED);
                $this->update();
            }
        } catch (StateException $_) {
            $this->update();
        }
    }
    /**
     * Get an object from an identifier
     * @param int|string $ident
     * @override
     * @return static
     * @phan-suppress PhanParamSignatureRealMismatchReturnType
     *   -> https://github.com/phan/phan/issues/2836
     */
    public static function getFromIdentifier($ident): self
    {
        $self = parent::getFromIdentifier($ident);
        $self->state = self::STATE_PARTIAL_FRESH;
        return $self;
    }
    
    
    /**
     * Get a plain object of the class with all the properties with value null
     * @return static Plain object with all the properties associated with the class
     */
    public static function getPlainObject()//:self
    {
        $self = parent::getPlainObject();
        $self->state = self::STATE_DUMMY;
        $self->IsActive = true;
        return $self;
    }
}
