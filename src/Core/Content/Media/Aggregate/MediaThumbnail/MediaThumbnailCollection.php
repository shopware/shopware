<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                      add(MediaThumbnailEntity $entity)
 * @method void                      set(string $key, MediaThumbnailEntity $entity)
 * @method MediaThumbnailEntity[]    getIterator()
 * @method MediaThumbnailEntity[]    getElements()
 * @method MediaThumbnailEntity|null get(string $key)
 * @method MediaThumbnailEntity|null first()
 * @method MediaThumbnailEntity|null last()
 */
class MediaThumbnailCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_thumbnail_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailEntity::class;
    }
}
