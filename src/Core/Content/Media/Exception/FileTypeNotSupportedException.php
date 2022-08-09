<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.5.0 - Will be removed. Use FileExtensionNotSupportedException or ThumbnailNotSupportedException instead
 */
class FileTypeNotSupportedException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_FILE_TYPE_NOT_SUPPORTED';
    }

    public function getStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
