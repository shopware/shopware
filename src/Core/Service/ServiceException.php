<?php declare(strict_types=1);

namespace Shopware\Core\Service;

use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\ContextSource;
use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\ResponseInterface;

#[Package('core')]
class ServiceException extends HttpException
{
    public const NOT_FOUND = 'SERVICE__NOT_FOUND';
    public const INTEGRATION_NOT_ALLOWED_TO_UPDATE_SERVICE = 'SERVICE__INTEGRATION_NOT_ALLOWED_TO_UPDATE_SERVICE';
    public const SERVICE_UPDATE_REQUIRES_ADMIN_API_SOURCE = 'SERVICE__UPDATE_REQUIRES_ADMIN_API_SOURCE';
    public const SERVICE_UPDATE_REQUIRES_INTEGRATION = 'SERVICE__UPDATE_REQUIRES_INTEGRATION';
    public const SERVICE_REQUEST_TRANSPORT_ERROR = 'SERVICE__TRANSPORT';
    public const SERVICE_MISSING_APP_VERSION_INFO = 'SERVICE__MISSING_APP_INFO';
    public const SERVICE_CANNOT_WRITE_APP = 'SERVICE__CANNOT_WRITE_APP';

    public const SERVICE_TOGGLE_ACTION_NOT_ALLOWED = 'SERVICE__TOGGLE_ACTION_NOT_ALLOWED';

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
        } catch (JsonException $e) {
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

    public static function cannotWriteAppToDestination(string $file): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SERVICE_CANNOT_WRITE_APP,
            'Error writing app zip to file "{{ file }}"',
            ['file' => $file]
        );
    }
}
