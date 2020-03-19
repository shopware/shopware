<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(MediaThumbnailSizeEntity $entity)
 * @method void                          set(string $key, MediaThumbnailSizeEntity $entity)
 * @method MediaThumbnailSizeEntity[]    getIterator()
 * @method MediaThumbnailSizeEntity[]    getElements()
 * @method MediaThumbnailSizeEntity|null get(string $key)
 * @method MediaThumbnailSizeEntity|null first()
 * @method MediaThumbnailSizeEntity|null last()
 */
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
