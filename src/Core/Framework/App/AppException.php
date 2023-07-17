<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppFlowException;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\InvalidAppFlowActionVariableException;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SystemConfig\Exception\XmlParsingException;
use Symfony\Component\HttpFoundation\Response;

#[Package('core')]
class AppException extends HttpException
{
    public const CANNOT_DELETE_COMPOSER_MANAGED = 'FRAMEWORK__APP_CANNOT_DELETE_COMPOSER_MANAGED';
    public const NOT_COMPATIBLE = 'FRAMEWORK__APP_NOT_COMPATIBLE';
    public const NOT_FOUND = 'FRAMEWORK__APP_NOT_FOUND';
    public const ALREADY_INSTALLED = 'FRAMEWORK__APP_ALREADY_INSTALLED';
    public const REGISTRATION_FAILED = 'FRAMEWORK__APP_REGISTRATION_FAILED';
    public const LICENSE_COULD_NOT_BE_VERIFIED = 'FRAMEWORK__APP_LICENSE_COULD_NOT_BE_VERIFIED';
    public const INVALID_CONFIGURATION = 'FRAMEWORK__APP_INVALID_CONFIGURATION';
    public const JWT_GENERATION_REQUIRES_CUSTOMER_LOGGED_IN = 'FRAMEWORK__APP_JWT_GENERATION_REQUIRES_CUSTOMER_LOGGED_IN';

    public static function cannotDeleteManaged(string $pluginName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CANNOT_DELETE_COMPOSER_MANAGED,
            'App {{ name }} is managed by Composer and cannot be deleted',
            ['name' => $pluginName]
        );
    }

    public static function notCompatible(string $pluginName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NOT_COMPATIBLE,
            'App {{ name }} is not compatible with this Shopware version',
            ['name' => $pluginName]
        );
    }

    public static function errorFlowCreateFromXmlFile(string $xmlFile, string $message): XmlParsingException
    {
        return new AppFlowException($xmlFile, $message);
    }

    public static function invalidAppFlowActionVariableException(
        string $appFlowActionId,
        string $param,
        string $message = '',
        int $code = 0
    ): InvalidAppFlowActionVariableException {
        return new InvalidAppFlowActionVariableException($appFlowActionId, $param, $message, $code);
    }

    public static function notFound(string $identifier): self
    {
        return new AppNotFoundException(
            Response::HTTP_NOT_FOUND,
            self::NOT_FOUND,
            'App with identifier "{{ identifier }}" not found',
            ['identifier' => $identifier]
        );
    }

    public static function alreadyInstalled(string $appName): self
    {
        return new AppAlreadyInstalledException(
            Response::HTTP_CONFLICT,
            self::ALREADY_INSTALLED,
            'App "{{ appName }}" is already installed',
            ['appName' => $appName]
        );
    }

    public static function registrationFailed(string $appName, string $reason, ?\Throwable $previous = null): self
    {
        return new AppRegistrationException(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::REGISTRATION_FAILED,
            'App registration for "{{ appName }}" failed: {{ reason }}',
            ['appName' => $appName, 'reason' => $reason],
            $previous
        );
    }

    public static function licenseCouldNotBeVerified(string $appName, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::LICENSE_COULD_NOT_BE_VERIFIED,
            'License for app "{{ appName }}" could not be verified',
            ['appName' => $appName],
            $previous
        );
    }

    public static function invalidConfiguration(string $appName, Error $error, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_CONFIGURATION,
            'Configuration of app "{{ appName }}" is invalid: {{ error }}',
            ['appName' => $appName, 'error' => $error->getMessage()],
            $previous
        );
    }

    public static function jwtGenerationRequiresCustomerLoggedIn(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::JWT_GENERATION_REQUIRES_CUSTOMER_LOGGED_IN,
            'JWT generation requires customer to be logged in'
        );
    }
}
