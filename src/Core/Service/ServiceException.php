<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Package('framework')]
class ServiceException extends HttpException
{
    public const NOT_FOUND = 'SERVICE__NOT_FOUND';
    public const INTEGRATION_NOT_ALLOWED_TO_UPDATE_SERVICE = 'SERVICE__INTEGRATION_NOT_ALLOWED_TO_UPDATE_SERVICE';
    public const SERVICE_UPDATE_REQUIRES_ADMIN_API_SOURCE = 'SERVICE__UPDATE_REQUIRES_ADMIN_API_SOURCE';
    public const SERVICE_UPDATE_REQUIRES_INTEGRATION = 'SERVICE__UPDATE_REQUIRES_INTEGRATION';
    public const SERVICE_REQUEST_TRANSPORT_ERROR = 'SERVICE__TRANSPORT';
    public const SERVICE_MISSING_APP_VERSION_INFO = 'SERVICE__MISSING_APP_INFO';
    public const SERVICE_CANNOT_WRITE_APP = 'SERVICE__CANNOT_WRITE_APP';

    public const SERVICE_MISSING_APP_SECRET_INFO = 'SERVICE__MISSING_APP_SECRET_INFO';

    public const SERVICE_TOGGLE_ACTION_NOT_ALLOWED = 'SERVICE__TOGGLE_ACTION_NOT_ALLOWED';

    public const COULD_NOT_FETCH_PERMISSIONS_REVISIONS = 'SERVICE__COULD_NOT_FETCH_PERMISSIONS_REVISIONS';

    public const INVALID_PERMISSIONS_REVISION_FORMAT = 'SERVICE__INVALID_PERMISSIONS_REVISION_FORMAT';

    public const SCHEDULED_TASK_NOT_REGISTERED = 'SCHEDULED_TASK_NOT_REGISTERED';

    public const SERVICE_REQUEST_FAILED = 'SERVICE__REQUEST_FAILED';

    public const NO_CURRENT_PERMISSIONS_CONSENT = 'SERVICE__NO_CURRENT_PERMISSIONS_CONSENT';

    public const SERVICES_NOT_INSTALLED = 'SERVICE__NOT_INSTALLED';

    public const SERVICE_INVALID_SERVICES_STATE = 'SERVICE__INVALID_SERVICES_STATE';

    public static function notFound(string $field, string $value): self
    {
        return new self(
            Response::HTTP_NOT_FOUND,
            self::NOT_FOUND,
            static::$couldNotFindMessage,
            [
                'entity' => 'service',
                'field' => $field,
                'value' => $value,
            ]
        );
    }

    public static function updateRequiresAdminApiSource(ContextSource $actualContextSource): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_UPDATE_REQUIRES_ADMIN_API_SOURCE,
            'Updating a service requires {{ class }}, but got {{ actualContextSource }}',
            [
                'class' => AdminApiSource::class,
                'actualContextSource' => $actualContextSource::class,
            ]
        );
    }

    public static function updateRequiresIntegration(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_UPDATE_REQUIRES_INTEGRATION,
            'Updating a service requires an integration',
        );
    }

    public static function requestFailed(ResponseInterface $response): self
    {
        try {
            $data = $response->toArray(false);
            $errors = $data['errors'] ?? [];
        } catch (JsonException) {
            $errors = [];
        }

        $message = 'Error performing request. Response code: ' . $response->getStatusCode();

        if (!empty($errors)) {
            $message .= '. Errors: ' . json_encode($errors, \JSON_THROW_ON_ERROR);
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_REQUEST_TRANSPORT_ERROR,
            $message,
            [],
        );
    }

    public static function toggleActionNotAllowed(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_TOGGLE_ACTION_NOT_ALLOWED,
            'Service is not allowed to toggle itself.',
        );
    }

    public static function requestTransportError(?\Throwable $previous = null): self
    {
        $message = 'Error performing request';

        if ($previous) {
            $message .= '. Error: ' . $previous->getMessage();
        }

        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_REQUEST_TRANSPORT_ERROR,
            $message,
            [],
            $previous
        );
    }

    public static function missingAppVersionInfo(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_MISSING_APP_VERSION_INFO,
            'Error downloading app. The version information was missing.'
        );
    }

    public static function missingAppSecretInfo(string $appId): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_MISSING_APP_SECRET_INFO,
            'Error creating client. The app secret information was missing. App ID: "{{ appId }}"',
            ['appId' => $appId]
        );
    }

    public static function cannotWriteAppToDestination(string $file): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_CANNOT_WRITE_APP,
            'Error writing app zip to file "{{ file }}"',
            ['file' => $file]
        );
    }

    public static function invalidPermissionsRevisionFormat(string $revision): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PERMISSIONS_REVISION_FORMAT,
            'The provided permissions revision "{{ revision }}" is not in the correct format Y-m-d.',
            ['revision' => $revision]
        );
    }

    public static function scheduledTaskNotRegistered(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SCHEDULED_TASK_NOT_REGISTERED,
            'Could not queue task "services.install" because it is not registered.',
        );
    }

    public static function invalidServicesState(): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::SERVICE_INVALID_SERVICES_STATE,
            'The services are in an invalid state. Cannot start if the consent is not given.',
        );
    }

    public static function serviceNotInstalled(string $name): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICES_NOT_INSTALLED,
            'The service is not installed.',
            ['name' => $name]
        );
    }

    public static function consentSaveFailed(string $getMessage): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_REQUEST_FAILED,
            'Could not save consent: ' . $getMessage
        );
    }

    public static function consentRevokeFailed(string $getMessage): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_REQUEST_FAILED,
            'Could not revoke consent: ' . $getMessage
        );
    }

    public static function noCurrentPermissionsConsent(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::NO_CURRENT_PERMISSIONS_CONSENT,
            'No current permissions consent found.',
        );
    }

    public static function invalidPermissionsContext(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_REQUEST_FAILED,
            'This action is only allowed from Admins.',
        );
    }

    /**
     * @param array<string, mixed> $json
     */
    public static function invalidPermissionConsentFormat(array $json): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_PERMISSIONS_REVISION_FORMAT,
            'The saved permissions consent is not in a valid format.',
            ['consent' => $json]
        );
    }
}
