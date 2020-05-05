<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\DatabaseTable\Exceptions;

use ArrayAccess;
use Countable;
use JsonSerializable;
use LogicException;
use PDOException;
use RuntimeException;

class MariaDBException extends DatabaseException implements ArrayAccess, Countable, JsonSerializable
{
    public static function create(PDOException $e): self
    {
        $errcode = $e->errorInfo[1];
        $errname = MariaDBExceptionData::CODES_TO_CLASSES[$errcode];
        /**
         * @psalm-var class-string<self>
         */
        $classname = self::class . "\\$errname";
        if (!preg_match(MariaDBExceptionData::REGEXES[$errcode], $e->errorInfo[2], $matches)) {
            throw new RuntimeException("Could not match regex " . MariaDBExceptionData::REGEXES[$errcode] . " to " . $e->errorInfo[2] . " for $errname");
        }
        array_shift($matches);
        return new $classname($matches);
    }
    /**
     * @var string[]
     */
    private $data = [];
    /**
     * @param string[] $data
     */
    protected function __construct(array $data)
    {
        $this->data = $data;
    }
    /**
     * @param string|int $offset
     */
    public function offsetExists($offset): bool
    {
        return is_int($offset) && array_key_exists($offset, $this->data);
    }
    /**
     * @param string|int $offset
     */
    public function offsetGet($offset): ?string
    {
        if (!$this->offsetExists($offset)) {
            return null;
        }
        return $this->data[$offset];
    }
    /**
     * @param string|int $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value): void
    {
        throw new LogicException("Cannot set on MariaDBException");
    }
    /**
     * @param string|int $offset
     */
    public function offsetUnset($offset): void
    {
        throw new LogicException("Cannot unset on MariaDBException");
    }
    public function count(): int
    {
        return count($this->data);
    }
    /**
     * @return array<string,mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->data;
    }
}
