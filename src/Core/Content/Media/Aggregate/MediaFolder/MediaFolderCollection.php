<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                   add(MediaFolderEntity $entity)
 * @method void                   set(string $key, MediaFolderEntity $entity)
 * @method MediaFolderEntity[]    getIterator()
 * @method MediaFolderEntity[]    getElements()
 * @method MediaFolderEntity|null get(string $key)
 * @method MediaFolderEntity|null first()
 * @method MediaFolderEntity|null last()
 */
class MediaFolderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_folder_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaFolderEntity::class;
    }
}
