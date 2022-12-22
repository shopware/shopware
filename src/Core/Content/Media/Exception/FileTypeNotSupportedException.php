<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\ShopwareHttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * @package content
 *
 * @deprecated tag:v6.5.0 - Will be removed. Use FileExtensionNotSupportedException or ThumbnailNotSupportedException instead
 */
class FileTypeNotSupportedException extends ShopwareHttpException
{
    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(
                __CLASS__,
                __METHOD__,
                'v6.5.0.0',
                'FileExtensionNotSupportedException::getErrorCode|ThumbnailNotSupportedException::getErrorCode'
            )
        );

        return 'CONTENT__MEDIA_FILE_TYPE_NOT_SUPPORTED';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.5.0.0',
            Feature::deprecatedMethodMessage(
                __CLASS__,
                __METHOD__,
                'v6.5.0.0',
                'FileExtensionNotSupportedException::getStatusCode|ThumbnailNotSupportedException::getStatusCode'
            )
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
