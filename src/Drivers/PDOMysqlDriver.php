<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable;

use ArrayIterator;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\Condition;
use GCCISWebProjects\Utilities\DatabaseTable\Exceptions\MariaDBException;
use GCCISWebProjects\Utilities\DatabaseTable\Properties\Property;
use GCCISWebProjects\Utilities\Iterators\RewindableIteratorFactory;
use PDO;
use PDOStatement;

/**
 * Mysql driver using PDO
 */
class PDOMysqlDriver implements IDatabaseDriver
{
    /**
     * @phan-var array<string, PDOStatement>
     */
    private $preparedStatements = [];
    private function getPreparedStatement(string $query): PDOStatement
    {
        if (!array_key_exists($query, $this->preparedStatements)) {
            $this->preparedStatements[$query] = $this->pdo->prepare($query);
        }
        return $this->preparedStatements[$query];
    }
    public static function escapeIdentifier(string $identifier): string
    {
        $replacements = ["`" => "\\`","\0" => ""];
        return '`' . str_replace(
            array_keys($replacements),
            array_values($replacements),
            $identifier
        ) . '`';
    }
    /**
     * PDO connection object
     *
     * @var PDO
     */
    private $pdo;
    /**
     * @phan-param array<int, mixed> $options Options to pass to PDO
     * @psalm-param array<int, mixed> $options Options to pass to PDO
     */
    public function __construct(
        string $host,
        string $username = null,
        string $password = null,
        array $options = []
    ) {
        if ($host[0] === "/") {
            $this->pdo = new PDO(
                "mysql:unix_socket=" . $host,
                $username,
                $password,
                $options
            );
        } else {
            $this->pdo = new PDO(
                "mysql:host=" . $host,
                $username,
                $password,
                $options
            );
        }
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    /**
     * @inheritDoc
     */
    public function create(
        string $database,
        string $class,
        array $data
    ): ?int {
        $escapedDatabase = self::escapeIdentifier($database);
        $escapedTable = self::escapeIdentifier($class::getTableName());
        $columns = array_keys($data);
        $columns = array_map(
            function (string $property): string {
                return self::escapeIdentifier($property);
            },
            $columns
        );
        $columns = implode(", ", $columns);
        $values = implode(", ", array_fill(0, count($data), "?"));
        $query = $this->getPreparedStatement(
            $qString = "INSERT INTO $escapedDatabase.$escapedTable " .
            "($columns) VALUES ($values);"
        );
        if (defined("PDO_MYSQL_DEBUG")) {
            echo "$qString " . json_encode(array_values($data)) . PHP_EOL;
        }
        try {
            $query->execute(array_values($data));
        } catch (\PDOException $e) {
            throw MariaDBException::create($e);
        }
        return (int)$this->pdo->lastInsertId();
    }
    
    // Structure: query nArgs arg1 arg2 ...
    /**
     * @psalm-var array<string, array<int, mixed>>
     */
    private $cache = [];
    /**
     * @param string[] $args
     */
    private function getCachedEntry(string $query, array $args): ?RewindableIteratorFactory
    {
        if (!array_key_exists($query, $this->cache)) {
            return null; // We've never run this query
        }
        if (!array_key_exists(count($args), $this->cache[$query])) {
            return null; //
        }
        $location = $this->cache[$query][count($args)];
        foreach ($args as $arg) {
            if (!array_key_exists($arg, $location)) {
                return null;
            }
            $location = $location[$arg];
        }
        return $location;
    }
    /**
     * @param string[] $args
     */
    private function setCachedEntry(string $query, array $args, RewindableIteratorFactory $it): void
    {
        if (!array_key_exists($query, $this->cache)) {
            $this->cache[$query] = [];
        }
        if (!array_key_exists(count($args), $this->cache[$query])) {
            $this->cache[$query][count($args)] = [];
        }
        $location = $this->cache[$query][count($args)];
        $lastKey = array_key_last($args);
        if (empty($args)) {
            $this->cache[$query][count($args)] = $it;
        }
        foreach ($args as $key => $arg) {
            if ($key === $lastKey) {
                $location[$arg] = $it;
                break;
            }
            if (!array_key_exists($arg, $location)) {
                $location[$arg] = [];
            }
            $location = $location[$arg];
        }
    }
    /**
     * @inheritDoc
     * @return RewindableIteratorFactory
     * @see https://wiki.php.net/rfc/covariant-returns-and-contravariant-parameters - can't declare return type as RewindableIteratorFactory until this is merged
     */
    public function read(
        string $database,
        string $class,
        Condition $condition,
        int $limit = null,
        int $offset = 0,
        array $order = [],
        array $asc = [],
        bool $prefetch = false
    ): iterable {
        $escapedDatabase = self::escapeIdentifier($database);
        $escapedTable = self::escapeIdentifier($class::getTableName());
        $where = "";
        $params = iterator_to_array($condition->getWhereClause($class, $where), false);
        $query = "SELECT * FROM $escapedDatabase." .
            "$escapedTable WHERE $where";
        if (!empty($order)) {
            $query .= " ORDER BY ";
            $first = true;
            foreach ($order as $i => $col) {
                if ($first) {
                    $first = false;
                } else {
                    $query .= ", ";
                }
                $column = ClassProperty::getProperty($class, $col);
                assert($column instanceof Property);
                $query .= self::escapeIdentifier($column->getDatabaseName()) . " " . (($asc[$i] ?? true) ? "ASC" : "DESC");
            }
        }
        if ($offset !== 0 || !is_null($limit)) {
            $query .= " LIMIT " . ($limit ?? PHP_INT_MAX);
        }
        if ($offset !== 0) {
            $query .= " OFFSET $offset";
        }
        $cachedResult = $this->getCachedEntry($query, $params);
        if ($cachedResult instanceof RewindableIteratorFactory) {
            return $cachedResult;
        }
        if (defined("PDO_MYSQL_DEBUG")) {
            echo "$query " . json_encode(array_values($params)) . PHP_EOL;
        }
        // Do not re-use an old statement, it may still be in use
        $statement = $this->pdo->prepare($query);
        try {
            $statement->execute($params);
        } catch (\PDOException $e) {
            throw MariaDBException::create($e);
        }
        $it = new RewindableIteratorFactory($prefetch ? new ArrayIterator($statement->fetchAll()) : $statement);
        $this->setCachedEntry($query, $params, $it);
        return $it;
    }
    /**
     * @inheritDoc
     */
    public function update(
        string $database,
        string $class,
        Condition $condition,
        array $data
    ): void {
        $escapedDatabase = self::escapeIdentifier($database);
        $escapedTable = self::escapeIdentifier($class::getTableName());
        $where = "";
        $params = array_merge(
            array_values($data),
            iterator_to_array($condition->getWhereClause($class, $where), false)
        );
        $sets = implode(", ", array_map(
            function (string $key): string {
                return self::escapeIdentifier($key) . "=?";
            },
            array_keys($data)
        ));
        $query = $this->getPreparedStatement(
            $qString = "UPDATE $escapedDatabase.$escapedTable" .
            " SET $sets WHERE $where"
        );
        if (defined("PDO_MYSQL_DEBUG")) {
            echo "$qString " . json_encode(array_values($params)) . PHP_EOL;
        }
        try {
            $query->execute($params);
        } catch (\PDOException $e) {
            throw MariaDBException::create($e);
        }
    }
    /**
     * @inheritDoc
     */
    public function delete(
        string $database,
        string $class,
        Condition $condition
    ): void {
        $escapedDatabase = self::escapeIdentifier($database);
        $escapedTable = self::escapeIdentifier($class::getTableName());
        $where = "";
        $params = iterator_to_array($condition->getWhereClause($class, $where), false);
        $query = $this->getPreparedStatement(
            $qString = "DELETE FROM $escapedDatabase.$escapedTable WHERE $where"
        );
        if (defined("PDO_MYSQL_DEBUG")) {
            echo "$qString " . json_encode(array_values($params)) . PHP_EOL;
        }
        try {
            $query->execute($params);
        } catch (\PDOException $e) {
            throw MariaDBException::create($e);
        }
    }
    public function query(string $query): PDOStatement
    {
        if (defined("PDO_MYSQL_DEBUG")) {
            echo "$query" . PHP_EOL;
        }
        try {
            return $this->pdo->query($query);
        } catch (\PDOException $e) {
            throw MariaDBException::create($e);
        }
    }
}
