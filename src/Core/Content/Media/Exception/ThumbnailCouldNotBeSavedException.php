<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Exception;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;

#[Package('content')]
class ThumbnailCouldNotBeSavedException extends ShopwareHttpException
{
    public function __construct(string $url)
    {
        parent::__construct(
            'Thumbnail could not be saved to location: {{ location }}',
            ['location' => $url]
        );
    }

    public function getErrorCode(): string
    {
        return 'CONTENT__MEDIA_THUMBNAIL_NOT_SAVED';
    }
}
