<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::thumbnailNotSupported instead
 */
#[Package('content')]
class ThumbnailNotSupportedException extends MediaException
{
    public function __construct(string $mediaId)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::thumbnailNotSupported instead')
        );

        parent::__construct(
            Response::HTTP_BAD_REQUEST,
            self::MEDIA_FILE_NOT_SUPPORTED_FOR_THUMBNAIL,
            'The file for media object with id {{ mediaId }} is not supported for creating thumbnails.',
            ['mediaId' => $mediaId]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::thumbnailNotSupported instead')
        );

        return 'CONTENT__MEDIA_FILE_NOT_SUPPORTED_FOR_THUMBNAIL';
    }

    public function getStatusCode(): int
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::thumbnailNotSupported instead')
        );

        return Response::HTTP_BAD_REQUEST;
    }
}
