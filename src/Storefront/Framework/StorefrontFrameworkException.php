<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @codeCoverageIgnore
 */
#[Package('framework')]
class StorefrontFrameworkException extends HttpException
{
    public const APP_TEMPLATE_FILE_NOT_READABLE = 'STOREFRONT__APP_TEMPLATE_NOT_READABLE';

    public const APP_REQUEST_NOT_AVAILABLE = 'STOREFRONT__APP_REQUEST_NOT_AVAILABLE';
    public const SALES_CHANNEL_CONTEXT_OBJECT_NOT_FOUND = 'STOREFRONT__SALES_CHANNEL_CONTEXT_OBJECT_NOT_FOUND';
    public const MEDIA_ILLEGAL_FILE_TYPE = 'STOREFRONT__MEDIA_ILLEGAL_FILE_TYPE';

    public const INVALID_ARGUMENT = 'STOREFRONT__INVALID_ARGUMENT';

    public static function appTemplateFileNotReadable(string $path): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_TEMPLATE_FILE_NOT_READABLE,
            'Unable to read file from: {{ path }}.',
            ['path' => $path]
        );
    }

    public static function appRequestNotAvailable(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::APP_REQUEST_NOT_AVAILABLE,
            'The "app.request" variable is not available.'
        );
    }

    public static function salesChannelContextObjectNotFound(): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::SALES_CHANNEL_CONTEXT_OBJECT_NOT_FOUND,
            'Missing sales channel context object',
        );
    }

    public static function fileTypeNotAllowed(string $mimeType, string $uploadType): self
    {
        return new self(
            Response::HTTP_INTERNAL_SERVER_ERROR,
            self::MEDIA_ILLEGAL_FILE_TYPE,
            'Type "{{ mimeType }}" of provided file is not allowed for {{ uploadType }}',
            ['mimeType' => $mimeType, 'uploadType' => $uploadType]
        );
    }

    public static function invalidArgument(string $message): self
    {
        return new self(
            Response::HTTP_BAD_REQUEST,
            self::INVALID_ARGUMENT,
            $message
        );
    }
}
