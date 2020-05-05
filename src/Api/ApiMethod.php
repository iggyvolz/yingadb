<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\Api;

use DateTime;
use GCCISWebProjects\Utilities\Api\ActionResponse;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperties;
use GCCISWebProjects\Utilities\ClassProperties\ClassProperty;
use GCCISWebProjects\Utilities\ClassProperties\Identifiable;
use GCCISWebProjects\Utilities\DatabaseTable\Condition\EqualCondition;
use GCCISWebProjects\Utilities\DatabaseTable\DatabaseTable;
use GCCISWebProjects\Utilities\Injectable;
use GCCISWebProjects\Utilities\Module\Module;
use GCCISWebProjects\Utilities\User\User;
use Iterator;
use LogicException;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use Traversable;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\DocBlock\Tags\BaseTag;
use phpDocumentor\Reflection\Types\ContextFactory;

class ApiMethod
{
    private ReflectionMethod $method;
    private ReflectionClass $class;
    private ?string $permission;
    private bool $needWrite;
    /** @var string[] */
    private array $noFillProps = [];
    private bool $noFetch;
    private function __construct(ReflectionMethod $method, DocBlock $db)
    {
        $this->method = $method;
        $this->class = $method->getDeclaringClass();
        $permissionTags = array_merge($db->getTagsByName("api-permission"), $db->getTagsByName("api-permission-write"));
        if (!empty($permissionTags) && $permissionTags[0] instanceof BaseTag) {
            $this->permission = (string)($permissionTags[0]->getDescription());
            $this->needWrite = $permissionTags[0]->getName() === "api-permission-write";
        } else {
            $this->permission = null;
            $this->needWrite = false;
        }
        $this->noFetch = !empty($db->getTagsByName("api-no-fetch"));
        // https://github.com/phan/phan/issues/3524
        if (($noFillPropsTag = ($db->getTagsByName("api-no-fill-props")[0] ?? null)) && $noFillPropsTag instanceof BaseTag) {
            $this->noFillProps = json_decode((string)($noFillPropsTag->getDescription()));
        }
    }

    /**
     * Checks if a user has permission to execute this method
     * @param User $user User to check
     * @return bool True if the user has permission, false otherwise
     */
    private function permissionCheck(User $user): bool
    {
        if (is_null($this->permission)) {
            return true;
        }
        $module = Module::get(new EqualCondition("Name", $this->permission));
        return $module && $user->hasPermission($module, $this->needWrite);
    }

    /**
     * Checks a builtin type to match a given type
     * @param string $type Type as regurned by ReflectionNamedType::getName
     * @param mixed $value Value to check
     * @return bool True if $value is an instance of $type, false otherwise
     */
    private static function checkBuiltinType(string $type, $value): bool
    {
        switch ($type) {
            case "int":
            case "integer":
                return is_int($value);
            case "float":
            case "double":
            case "real":
                return is_float($value);
            case "bool":
            case "boolean":
                return is_bool($value);
            case "string":
                return is_string($value);
            case "array":
                return is_array($value);
            default:
                throw new LogicException("Unknown type $type");
        }
    }

    /**
     * @param array<string,scalar|null> $args
     * @return mixed
     */
    private static function getParameterValue(ReflectionParameter $parameter, User $currentUser, array $args)
    {
        $name = $parameter->getName();
        $type = $parameter->getType();
        // Check special values
        // TODO support ReflectionUnionType in 8.0
        if (!$type instanceof ReflectionNamedType) {
            throw new LogicException("Untyped parameter $name");
        }
        $typeName = $type->getName();
        // Support special names: User $currentUser
        if ($typeName === User::class && $name === "currentUser") {
            return $currentUser;
        }
        // Read the class from the type
        $class = null;
        try {
            /** @psalm-suppress ArgumentTypeCoercion */
            $class = new ReflectionClass($typeName);
            if ($class->implementsInterface(Injectable::class)) {
                /** @psalm-var class-string<Injectable> $typeName */
                return $typeName::get();
            }
        } catch (ReflectionException $_) {
            // Not injectable
        }
        // Attempt to fill from $args
        if (array_key_exists($name, $args)) {
            $value = $args[$name];
            if ($value === null) {
                if ($type->allowsNull()) {
                    return null;
                } else {
                    throw ActionResponse::invalidValue($name);
                }
            }
            if ($type->isBuiltin()) {
                if (self::checkBuiltinType($typeName, $value)) {
                    return $value;
                } else {
                    throw ActionResponse::invalidValue($name);
                }
            } elseif (!is_null($class) && $class->isSubclassOf(Identifiable::class)) {
                if (is_string($value) || is_int($value)) {
                    /** @psalm-var class-string<Identifiable> */
                    $typeName = $type->getName();
                    return $typeName::getFromIdentifier($value);
                } else {
                    throw ActionResponse::invalidValue($name);
                }
            } elseif ($type->getName() === DateTime::class) {
                return DateTime::createFromFormat("U", strval($value));
            } else {
                throw new LogicException("Unsupported type " . $typeName . " for $name");
            }
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        throw ActionResponse::requiredVariableNotSet($name);
    }

    /**
     * Invoke the API method
     * @param User $currentUser User executing the command
     * @param array<string,scalar> $args Args passed by the user
     */
    public function invoke(User $currentUser, array $args): ActionResponse
    {
        if (!$this->permissionCheck($currentUser)) {
            return ActionResponse::permissionFailure();
        }
        //  Save identifier name for later
        $identifierName = null;
        if ($this->method->isStatic()) {
            $object = null;
        } elseif (!$this->noFetch && $this->class->isSubclassOf(Identifiable::class)) {
            $identifierName = Identifiable::getIdentifierName($this->class->getName());
            if (array_key_exists($identifierName, $args)) {
                $object = $this->class->getMethod("getFromIdentifier")->invoke(null, $args[$identifierName]);
                if ($object instanceof DatabaseTable && !$object->fill()) {
                    // Object isn't valid
                    return ActionResponse::invalidValue($identifierName);
                }
            } else {
                return ActionResponse::requiredVariableNotSet($identifierName);
            }
        } else {
            $object = $this->class->newInstance();
        }
        $executionParameters = array_map(/** @return mixed */function (ReflectionParameter $parameter) use ($currentUser, $args, $object, $identifierName) {
            $value = self::getParameterValue($parameter, $currentUser, $args);
            // If there is a property on this class with the same name, set that to the value of the argument
            // Don't set identifier, as it is already set and setting it again causes state issues with DatabaseTable
            $parameterName = $parameter->getName();
            if (
                !in_array($parameterName, $this->noFillProps) &&
                !is_null($object) &&
                $object instanceof ClassProperties &&
                !is_null(ClassProperty::getProperty($this->class->getName(), $parameterName)) &&
                $parameterName !== $identifierName
            ) {
                $object->$parameterName = $value;
            }
            return $value;
        }, $this->method->getParameters());
        $ret = $this->method->invoke($object, ...$executionParameters);
        if ($ret instanceof Traversable) {
            $ret = iterator_to_array($ret, true);
        }
        return ActionResponse::Success($ret);
    }

    /**
     * Get all API v2 methods defined
     * @return Iterator<self> Iterator over all API methods
     */
    public static function getAll(): Iterator
    {
        $factory  = DocBlockFactory::createInstance();
        $contextfactory = new ContextFactory();
        foreach (get_declared_classes() as $class) {
            if (strpos($class, "GCCISWebProjects\\") !== 0) {
                continue; // Don't take external classes
            }
            if (strpos($class, "GCCISWebProjects\\Utilities\\DatabaseTable\\Exceptions") === 0) {
                continue; // Don't parse through all exception classes
            }
            $reflClass = new ReflectionClass($class);
            foreach ($reflClass->getMethods() as $method) {
                if (!$method->isPublic() || empty($method->getDocComment())) {
                    continue;
                }
                // Read the docblock off the method
                $docblock = $factory->create($method);
                $apiTag = $docblock->getTagsByName("api")[0] ?? null;
                if (!$apiTag || !$apiTag instanceof BaseTag) {
                    continue;
                }
                $methodName = (string)($apiTag->getDescription());
                [$methodName] = explode("\n", $methodName);
                yield $methodName => new self($method, $docblock);
            }
        }
    }
}
