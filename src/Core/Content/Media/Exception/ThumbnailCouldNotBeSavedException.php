<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

/**
 * @deprecated tag:v6.6.0 - will be removed, use MediaException::thumbnailCouldNotBeSaved instead
 */
#[Package('content')]
class ThumbnailCouldNotBeSavedException extends ShopwareHttpException
{
    public function __construct(string $url)
    {
        Feature::triggerDeprecationOrThrow(
            'v6.6.0.0',
            Feature::deprecatedClassMessage(self::class, 'v6.6.0.0', 'use MediaException::thumbnailCouldNotBeSaved instead')
        );

        parent::__construct(
            'Thumbnail could not be saved to location: {{ location }}',
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
