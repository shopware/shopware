<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaThumbnailSizeEntity>
 */
#[Package('content')]
class MediaThumbnailSizeCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_thumbnail_size_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailSizeEntity::class;
    }
}
