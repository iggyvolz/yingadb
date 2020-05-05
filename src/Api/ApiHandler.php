<?php

declare(strict_types=1);

namespace GCCISWebProjects\Utilities\Api;

use GCCISWebProjects\Utilities\ClassProperties\Initializable;
use GCCISWebProjects\Utilities\DatabaseTable\Exceptions\MariaDBException;
use GCCISWebProjects\Utilities\User\User;
use Throwable;

class ApiHandler implements Initializable
{
    /**
     * @var ApiMethod[]
     */
    private static array $apiMethods = [];
    public static function init(): void
    {
        self::$apiMethods = iterator_to_array(ApiMethod::getAll());
    }
    /**
     * @api foo
     * @api-permission foo
     */
    public static function dummy(string $requiredParam): string
    {
        return $requiredParam;
    }
    private static function getApiMethod(string $method): ?ApiMethod
    {
        if (empty(self::$apiMethods)) {
            self::init();
        }
        return self::$apiMethods[$method] ?? null;
    }
    /**
     * Handle an API request
     * @param string $method Method that was requested
     * @param null|string $authToken Token sent along with request
     * @param array<string,string> $postData Data that was posted to the page
     */
    private static function handleApiRequestInternal(string $method, string $authToken = null, array $postData = []): ActionResponse
    {
        try {
            $method = self::getApiMethod($method);
            if (!$method) {
                return ActionResponse::methodNotFound();
            }
            // Check auth token
            $user = User::getFromAuthToken($authToken);
            if (!$user) {
                return ActionResponse::authFailure();
            }
            return $method->invoke($user, $postData);
        } catch (MariaDBException $e) {
            return ActionResponse::dbError($e);
        } catch (ActionResponse $e) {
            return $e;
        } catch (Throwable $e) {
            return ActionResponse::uncaughtError($e);
        }
    }
    /**
     * Handle an API request as posted to the page
     */
    public static function handleApiRequest(): void
    {
        $authToken = $_SERVER["HTTP_X_AUTH_TOKEN"] ?? null;
        $method = $_SERVER["HTTP_X_API_METHOD"] ?? "";
        $postData = json_decode(file_get_contents("php://input"), true);
        if (!is_array($postData)) {
            $postData = [];
        }
        self::handleApiRequestInternal($method, $authToken, $postData)->send();
    }
}
