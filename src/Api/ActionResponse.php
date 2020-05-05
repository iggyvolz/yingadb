<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\Api;

use Exception;
use GCCISWebProjects\Utilities\DatabaseTable\Exceptions\MariaDBException;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use Throwable;

/**
 * Class ActionResponse
 * @property-read int $Code The status code to return
 * @property-read string|null $Message The message to return
 * @property-read int $Http the HTTP status code to return
 * @property-read mixed $Data Data sent to client
 */
class ActionResponse extends Exception implements JsonSerializable
{
    private string $statusCode;
    private int $http;
    /**
     * @var mixed
     */
    private $data;
    /**
     * Construct an ActionResponse
     * @param string $statusCode The error code to return
     * @param int $http The HTTP code to return
     * @param mixed $data Data to be sent to the user
     * @param Throwable|null $previous Exception which caused this exception
     */
    private function __construct(string $statusCode, int $http, $data = null, ?Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        $this->http = $http;
        $this->data = $data;
        parent::__construct(is_string($data) ? $data : "", $http, $previous);
    }

    /**
     * Get a property from the class
     * @param string $name Name of the property
     * @return mixed Result of the property (see docblock)
     * @throws InvalidArgumentException Desired property not in docblock
     */
    public function __get(string $name)
    {
        $reflection = new ReflectionClass(self::class);
        $comment = $reflection->getDocComment();
        preg_match_all('/@property(?:-read)? [^ ]+ \\$([^ ]+)/', $comment, $properties);
        $properties = $properties[1];
        if (in_array($name, $properties)) {
            // Return property
            $name = lcfirst($name);
            return $this->$name;
        }
        throw new InvalidArgumentException("Property $name not found on " . self::class);
    }

    /**
     * Get a property from the class
     * @param string $name Name of the property
     * @param mixed $value Resultant value of the property
     * @throws InvalidArgumentException Desired property not in docblock
     * @return void
     */
    public function __set(string $name, $value): void
    {
        $reflection = new ReflectionClass(self::class);
        $comment = $reflection->getDocComment();
        preg_match_all("/@property(-write)? [^ ]+ \$([^ ]+)/", $comment, $properties);
        $properties = $properties[2];
        if (in_array($name, $properties)) {
            // Return property
            $name = lcfirst($name);
            $this->$name = $value;
        }
        throw new InvalidArgumentException("Property $name not found on " . self::class);
    }

    /**
     * Returns with Success
     * @param mixed $data Message to display to user or object to be handled by code
     * @return ActionResponse Response object
     */
    public static function success($data = null): ActionResponse
    {
        return new static("SUCCESS", 200, $data);
    }

    /**
     * Returns with Required Variable Not Set
     * @param string $variable Variable that was not set
     * @return ActionResponse Response object
     */
    public static function requiredVariableNotSet(string $variable): ActionResponse
    {
        return new static("REQUIRED_VARIABLE_NOT_SET", 400, $variable);
    }

    /**
     * Returns with Invalid Value
     * @param string $variable Variable that had an invalid value
     * @return ActionResponse Response object
     */
    public static function invalidValue(string $variable): ActionResponse
    {
        return new static("INVALID_VALUE", 400, $variable);
    }

    /**
     * Returns with Method Not Found
     * @return ActionResponse Response object
     */
    public static function methodNotFound(): ActionResponse
    {
        return new static("METHOD_NOT_FOUND", 404);
    }

    /**
     * Gives a DB error
     * @param MariaDBException $e exception that was thrown by the DB
     * @return ActionResponse Response object
     */
    public static function dbError(MariaDBException $e): ActionResponse
    {
        return new static("UNCAUGHT_DB_ERROR", 500, get_class($e));
    }

    /**
     * Gives an Uncaught Error (ex. PHP error)
     * @return ActionResponse Response object
     */
    public static function uncaughtError(Throwable $e): ActionResponse
    {
        ob_start();
        debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        $bt = ob_get_clean();
        $str = get_class($e) . " - " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "  $bt";
        return new static("UNCAUGHT_ERROR", 500, $str);
    }

    /**
     * Thrown when the auth token fails to verify
     * @return ActionResponse Response object
     */
    public static function authFailure(): ActionResponse
    {
        return new static("AUTH_FAILURE", 403);
    }

    /**
     * Given when contents were not updated
     * @return ActionResponse Response object
     */
    public static function noUpdate(): ActionResponse
    {
        return new static("NO_UPDATE", 200);
    }

    /**
     * Custom error to the user
     * Represents an error that the user made
     * @param string $msg Text to be displayed to the user
     * @return ActionResponse Response object
     */
    public static function customUserError(string $msg): ActionResponse
    {
        return new static("CUSTOM_USER_ERROR", 400, $msg);
    }

    /**
     * Permission failure
     * @return ActionResponse
     */
    public static function permissionFailure(): ActionResponse
    {
        return new static("PERMISSION_FAILURE", 403);
    }

    /**
     * Sends the ActionResponse to the browser
     */
    public function send(): void
    {
        http_response_code($this->Http);
        // @phan-suppress-next-line PhanTypeSuspiciousEcho
        echo $this;
    }

    /**
     * @return array
     * @phan-return array{code:string,data:mixed}
     */
    public function jsonSerialize(): array
    {
        return [
            "code" => $this->statusCode,
            "data" => $this->data
        ];
    }

    /** @psalm-suppress InvalidToString - https://github.com/vimeo/psalm/issues/2996 */
    public function __toString(): string
    {
        return json_encode($this, JSON_THROW_ON_ERROR);
    }
}
