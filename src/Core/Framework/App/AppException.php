<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppFlowException;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Exception\InvalidAppFlowActionVariableException;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\Feature;
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
    public const FEATURES_REQUIRE_APP_SECRET = 'FRAMEWORK__APP_FEATURES_REQUIRE_APP_SECRET';
    public const APP_SECRET_MISSING = 'FRAMEWORK__APP_SECRET_MISSING';
    public const ACTION_BUTTON_PROCESS_EXCEPTION = 'FRAMEWORK__SYNC_ACTION_PROCESS_INTERRUPTED';
    public const INSTALLATION_FAILED = 'FRAMEWORK__APP_INSTALLATION_FAILED';
    public const XML_PARSE_ERROR = 'FRAMEWORK_APP__XML_PARSE_ERROR';
    public const MISSING_REQUEST_PARAMETER_CODE = 'FRAMEWORK__APP_MISSING_REQUEST_PARAMETER';
    final public const APP_PAYMENT_INVALID_TRANSACTION_ID = 'APP_PAYMENT__INVALID_TRANSACTION_ID';
    final public const APP_PAYMENT_INTERRUPTED = 'APP_PAYMENT__INTERRUPTED';
    public const NO_SOURCE_SUPPORTS = 'FRAMEWORK__APP_NO_SOURCE_SUPPORTS';
    public const CANNOT_MOUNT_APP_FILESYSTEM = 'FRAMEWORK__CANNOT_MOUNT_APP_FILESYSTEM';

    public const CHECKOUT_GATEWAY_PAYLOAD_INVALID_CODE = 'FRAMEWORK__APP_CHECKOUT_GATEWAY_PAYLOAD_INVALID';

    /**
     * @internal will be removed once store extensions are installed over composer
     */
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

    /**
     * @deprecated tag:v6.7.0 - Will be removed use AppException::createFromXmlFileFlowError instead
     */
    public static function errorFlowCreateFromXmlFile(string $xmlFile, string $message): XmlParsingException
    {
        Feature::triggerDeprecationOrThrow(
            'v6.7.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.7.0.0', 'AppException::createFromXmlFileFlowError')
        );

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
        return static::notFoundByField($identifier);
    }

    public static function notFoundByField(string $value, string $field = 'identifier'): self
    {
        return new AppNotFoundException(
            Response::HTTP_NOT_FOUND,
            self::NOT_FOUND,
            self::$couldNotFindMessage,
            ['entity' => 'app', 'field' => $field, 'value' => $value]
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

    /**
     * @param array<string> $features
     */
    public static function appSecretRequiredForFeatures(string $appName, array $features): self
    {
        $featuresAsString = \count($features) < 3
            ? implode(' and ', $features)
            : sprintf('%s and %s', implode(', ', \array_slice($features, 0, -1)), array_pop($features));

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::FEATURES_REQUIRE_APP_SECRET,
            'App "{{ appName }}" could not be installed/updated because it uses features {{ features }} but has no secret',
            ['appName' => $appName, 'features' => $featuresAsString],
        );
    }

    public static function appSecretMissing(string $appName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_SECRET_MISSING,
            'App secret is missing for app {{ appName }}',
            ['appName' => $appName]
        );
    }

    public static function actionButtonProcessException(string $actionId, string $message, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::ACTION_BUTTON_PROCESS_EXCEPTION,
            'The synchronous action (id: {{ actionId }}) process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            ['errorMessage' => $message, 'actionId' => $actionId],
            $e
        );
    }

    public static function installationFailed(string $appName, string $reason): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INSTALLATION_FAILED,
            'App installation for "{{ appName }}" failed: {{ reason }}',
            ['appName' => $appName, 'reason' => $reason],
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function createFromXmlFileFlowError(string $xmlFile, string $message, ?\Throwable $previous = null): self|AppFlowException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new AppFlowException($xmlFile, $message);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse file "{{ file }}". Message: {{ message }}',
            ['file' => $xmlFile, 'message' => $message],
            $previous
        );
    }

    /**
     * @deprecated tag:v6.7.0 - reason:return-type-change - Will only return `self` in the future
     */
    public static function xmlParsingException(string $file, string $message): self|XmlParsingException
    {
        if (!Feature::isActive('v6.7.0.0')) {
            return new XmlParsingException($file, $message);
        }

        return AppXmlParsingException::cannotParseFile($file, $message);
    }

    public static function missingRequestParameter(string $parameterName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::MISSING_REQUEST_PARAMETER_CODE,
            'Parameter "{{ parameterName }}" is missing.',
            ['parameterName' => $parameterName]
        );
    }

    public static function checkoutGatewayPayloadInvalid(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CHECKOUT_GATEWAY_PAYLOAD_INVALID_CODE,
            'The checkout gateway payload is invalid'
        );
    }

    public static function interrupted(string $errorMessage, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_PAYMENT_INTERRUPTED,
            'The app payment process was interrupted due to the following error:' . \PHP_EOL . '{{ errorMessage }}',
            [
                'errorMessage' => $errorMessage,
            ],
            $e
        );
    }

    public static function invalidTransaction(string $transactionId, ?\Throwable $e = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_PAYMENT_INVALID_TRANSACTION_ID,
            'The transaction with id {{ transactionId }} is invalid or could not be found.',
            ['transactionId' => $transactionId],
            $e
        );
    }

    public static function noSourceSupports(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_SOURCE_SUPPORTS,
            'App is not supported by any source.',
        );
    }

    public static function sourceDoesNotExist(string $sourceClassName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::NO_SOURCE_SUPPORTS,
            'The source "{{ sourceClassName }}" does not exist',
            [
                'sourceClassName' => $sourceClassName,
            ]
        );
    }

    public static function cannotMountAppFilesystem(string $appName, HttpException $exception): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_MOUNT_APP_FILESYSTEM,
            'Cannot mount a filesystem for App "{{ app }}". Error: "{{ error }}"',
            ['app' => $appName, 'error' => $exception->getMessage()],
            $exception
        );
    }
}
