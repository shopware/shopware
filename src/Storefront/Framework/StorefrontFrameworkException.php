<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework;

use Shopware\Core\Framework\HttpException;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

#[Package('storefront')]
class StorefrontFrameworkException extends HttpException
{
    public const APP_TEMPLATE_FILE_NOT_READABLE = 'STOREFRONT__APP_TEMPLATE_NOT_READABLE';

    public const APP_REQUEST_NOT_AVAILABLE = 'STOREFRONT__APP_REQUEST_NOT_AVAILABLE';

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
}
