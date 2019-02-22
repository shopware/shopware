<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @method void                                add(MediaFolderConfigurationEntity $entity)
 * @method void                                set(string $key, MediaFolderConfigurationEntity $entity)
 * @method MediaFolderConfigurationEntity[]    getIterator()
 * @method MediaFolderConfigurationEntity[]    getElements()
 * @method MediaFolderConfigurationEntity|null get(string $key)
 * @method MediaFolderConfigurationEntity|null first()
 * @method MediaFolderConfigurationEntity|null last()
 */
class MediaFolderConfigurationCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MediaFolderConfigurationEntity::class;
    }
}
