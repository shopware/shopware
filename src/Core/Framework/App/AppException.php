<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App;

use GuzzleHttp\Exception\RequestException;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\App\Exception\AppAlreadyInstalledException;
use Shopware\Core\Framework\App\Exception\AppNotFoundException;
use Shopware\Core\Framework\App\Exception\AppRegistrationException;
use Shopware\Core\Framework\App\Exception\AppXmlParsingException;
use Shopware\Core\Framework\App\Exception\InvalidAppFlowActionVariableException;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\Exception\UserAbortedCommandException;
use Shopware\Core\Framework\App\ShopId\FingerprintComparisonResult;
use Shopware\Core\Framework\App\Validation\Error\Error;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('framework')]
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
    public const USER_ABORTED = 'FRAMEWORK__APP_USER_ABORTED';
    public const CANNOT_READ_FILE = 'FRAMEWORK__APP_CANNOT_READ_FILE';
    public const APP_ACTION_NOT_FOUND = 'FRAMEWORK__APP_ACTION_NOT_FOUND';
    public const JWKS_KEY_NOT_FOUND = 'FRAMEWORK__APP_JWKS_KEY_NOT_FOUND';
    final public const APP_UNALLOWED_HOST = 'APP__UNALLOWED_HOST';
    final public const INVALID_ARGUMENT = 'APP__INVALID_ARGUMENT';
    final public const APP_CREATE_COMMAND_VALIDATION_ERROR = 'FRAMEWORK__APP_CREATE_COMMAND_VALIDATION_ERROR';
    final public const APP_DIRECTORY_ALREADY_EXISTS = 'FRAMEWORK__APP_DIRECTORY_ALREADY_EXISTS';
    final public const APP_DIRECTORY_CREATION_FAILED = 'FRAMEWORK__APP_DIRECTORY_CREATION_FAILED';
    final public const APP_GATEWAY_NOT_CONFIGURED = 'FRAMEWORK__APP_GATEWAY_NOT_CONFIGURED';
    final public const APP_GATEWAY_REQUEST_FAILED = 'FRAMEWORK__APP_CONTEXT_GATEWAY_REQUEST_FAILED';
    final public const APP_RESTRICT_DELETE_PREVENTS_DEACTIVATION = 'FRAMEWORK__APP_RESTRICT_DELETE_PREVENTS_DEACTIVATION';
    final public const CONFLICTING_PRIVILEGE_UPDATE = 'FRAMEWORK__APP_CONFLICTING_PRIVILEGE_UPDATE';
    final public const INVALID_PERMISSIONS = 'FRAMEWORK__APP_INVALID_PERMISSIONS';
    final public const REQUIRES_ADMIN_API_SOURCE = 'FRAMEWORK__APP_ACTION_REQUIRES_ADMIN_API_SOURCE';
    final public const MISSING_USER_IN_CONTEXT_SOURCE = 'FRAMEWORK__APP_MISSING_USER_IN_CONTEXT_SOURCE';
    final public const INTEGRATION_MISSING = 'FRAMEWORK__APP_MISSING_INTEGRATION';
    final public const SHOP_ID_CHANGE_SUGGESTED = 'FRAMEWORK__APP_SHOP_ID_CHANGE_SUGGESTED';
    final public const APP_URL_NOT_CONFIGURED = 'FRAMEWORK__APP_URL_NOT_CONFIGURED';
    final public const INVALID_SHOP_ID_CONFIGURATION = 'FRAMEWORK__APP_INVALID_SHOP_ID_CONFIGURATION';

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
            : \sprintf('%s and %s', implode(', ', \array_slice($features, 0, -1)), array_pop($features));

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

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement in next major version as it is unused
     */
    public static function installationFailed(string $appName, string $reason): self
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INSTALLATION_FAILED,
            'App installation for "{{ appName }}" failed: {{ reason }}',
            ['appName' => $appName, 'reason' => $reason],
        );
    }

    public static function createFromXmlFileFlowError(string $xmlFile, string $message, ?\Throwable $previous = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::XML_PARSE_ERROR,
            'Unable to parse file "{{ file }}". Message: {{ message }}',
            ['file' => $xmlFile, 'message' => $message],
            $previous
        );
    }

    public static function xmlParsingException(string $file, string $message): self
    {
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

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement in next major version as it is unused
     */
    public static function checkoutGatewayPayloadInvalid(): self
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

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

    /**
     * @deprecated tag:v6.8.0 - Will be removed without replacement in next major version as it is unused
     */
    public static function inAppPurchaseGatewayUrlEmpty(): self
    {
        Feature::triggerDeprecationOrThrow('v6.8.0.0', Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0'));

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_CONFIGURATION,
            'No In-App Purchases gateway url set. Please update your manifest file.',
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

    public static function userAborted(): self
    {
        return new UserAbortedCommandException(
            Response::HTTP_BAD_REQUEST,
            self::USER_ABORTED,
            'User aborted operation'
        );
    }

    public static function cannotReadFile(string $file): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::CANNOT_READ_FILE,
            'Unable to read file: "{{ file }}"',
            ['file' => $file]
        );
    }

    public static function actionNotFound(): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::APP_ACTION_NOT_FOUND,
            'The requested app action does not exist',
        );
    }

    /**
     * @deprecated tag:v6.8.0 - Will be removed. Use `StoreException::jwksNotFound` instead
     */
    public static function jwksNotFound(?\Throwable $e = null): self
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.8.0.0'),
        );

        return new self(
            statusCode: Response::HTTP_INTERNAL_SERVER_ERROR,
            errorCode: self::JWKS_KEY_NOT_FOUND,
            message: 'Unable to retrieve JWKS key',
            previous: $e
        );
    }

    public static function hostNotAllowed(string $host, string $appName): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_UNALLOWED_HOST,
            'The host "{{ host }}" you tried to call is not listed in the allowed hosts in the manifest file for app "{{ appName }}".',
            ['host' => $host, 'appName' => $appName]
        );
    }

    public static function appNotFoundByName(mixed $appName): self
    {
        return self::notFoundByField($appName, 'name');
    }

    public static function invalidArgument(string $string): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ARGUMENT,
            $string
        );
    }

    public static function createCommandValidationError(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_CREATE_COMMAND_VALIDATION_ERROR,
            $message
        );
    }

    public static function directoryAlreadyExists(string $appName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_DIRECTORY_ALREADY_EXISTS,
            'Directory for app "{{ appName }}" already exists',
            ['appName' => $appName]
        );
    }

    public static function directoryCreationFailed(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_DIRECTORY_CREATION_FAILED,
            'Unable to create directory "{{ path }}". Please check permissions',
            ['path' => $path]
        );
    }

    public static function gatewayNotConfigured(string $appName, string $gateway): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_GATEWAY_NOT_CONFIGURED,
            'Gateway "{{ gateway }}" is not configured for app "{{ appName }}". Please check the manifest file',
            ['appName' => $appName, 'gateway' => $gateway]
        );
    }

    public static function gatewayRequestFailed(string $appName, string $gateway, ?RequestException $requestException = null): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_GATEWAY_REQUEST_FAILED,
            'Request from app "{{ appName }}" to gateway "{{ gateway }}" failed.',
            ['appName' => $appName, 'gateway' => $gateway],
            $requestException
        );
    }

    public static function restrictDeletePreventsDeactivation(string $appName): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_RESTRICT_DELETE_PREVENTS_DEACTIVATION,
            'App "{{ name }}" has some data that restricts deletion, please remove the data first or uninstall the app without the `keepUserData` option.',
            ['name' => $appName]
        );
    }

    public static function conflictingPrivilegeUpdate(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::CONFLICTING_PRIVILEGE_UPDATE,
            'A privilege cannot be present in both the accept and revoke lists simultaneously.'
        );
    }

    public static function invalidPrivileges(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PERMISSIONS,
            'For each accept, or revoke, expected a list of privileges in the format "category:read"',
        );
    }

    /**
     * @param class-string<ContextSource> $expectedContextSource
     * @param class-string<ContextSource> $actualContextSource
     */
    public static function invalidContextSource(string $expectedContextSource, string $actualContextSource): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::REQUIRES_ADMIN_API_SOURCE,
            'Expected context source to be "{{ expectedContextSource }}" but got "{{ actualContextSource }}".',
            [
                'expectedContextSource' => $expectedContextSource,
                'actualContextSource' => $actualContextSource,
            ],
        );
    }

    /**
     * @param class-string<ContextSource> $contextSource
     */
    public static function missingUserInContextSource(
        string $contextSource,
        ?\Throwable $previous = null
    ): self {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::MISSING_USER_IN_CONTEXT_SOURCE,
            'No user available in context source "{{ contextSource }}"',
            ['contextSource' => $contextSource],
            $previous,
        );
    }

    public static function missingIntegration(): self
    {
        return new self(
            Response::HTTP_FORBIDDEN,
            self::INTEGRATION_MISSING,
            'Forbidden. Not a valid integration source.',
        );
    }

    public static function shopIdChangeSuggested(FingerprintComparisonResult $comparisonResult): self
    {
        return new ShopIdChangeSuggestedException($comparisonResult);
    }

    public static function appUrlNotConfigured(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::APP_URL_NOT_CONFIGURED,
            'The environment variable "APP_URL" is not set. Please set it to the URL to your Admin API.'
        );
    }

    public static function invalidShopIdConfiguration(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::INVALID_SHOP_ID_CONFIGURATION,
            'The configuration values for "core.app.shopIdV2" and "core.app.shopId" in the system config are invalid.'
        );
    }
}
