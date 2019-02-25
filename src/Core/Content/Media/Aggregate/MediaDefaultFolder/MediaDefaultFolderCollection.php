<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                          add(MediaDefaultFolderEntity $entity)
 * @method void                          set(string $key, MediaDefaultFolderEntity $entity)
 * @method MediaDefaultFolderEntity[]    getIterator()
 * @method MediaDefaultFolderEntity[]    getElements()
 * @method MediaDefaultFolderEntity|null get(string $key)
 * @method MediaDefaultFolderEntity|null first()
 * @method MediaDefaultFolderEntity|null last()
 */
class MediaDefaultFolderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MediaDefaultFolderEntity::class;
    }
}
