<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaFolderConfigurationEntity>
 */
#[Package('content')]
class MediaFolderConfigurationCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_folder_configuration_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaFolderConfigurationEntity::class;
    }
}
