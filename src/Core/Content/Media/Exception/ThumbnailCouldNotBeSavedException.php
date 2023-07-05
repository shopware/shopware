<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\HttpFoundation\Response;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::thumbnailCouldNotBeSaved instead
 */
#[Package('content')]
class ThumbnailCouldNotBeSavedException extends MediaException
{
    public function __construct(string $url)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::thumbnailCouldNotBeSaved instead')
        );

        parent::__construct(
            Response::HTTP_CONFLICT,
            self::MEDIA_THUMBNAIL_NOT_SAVED,
            'Thumbnail could not be saved to location: {{ location }}.',
            ['location' => $url]
        );
    }

    public function getErrorCode(): string
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.6.0.0', 'use MediaException::thumbnailCouldNotBeSaved instead')
        );

        return 'CONTENT__MEDIA_THUMBNAIL_NOT_SAVED';
    }
}
