<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\Log\Package;

/**
 * @extends EntityCollection<MediaDefaultFolderEntity>
 */
#[Package('content')]
class MediaDefaultFolderCollection extends EntityCollection
{
    public function getApiAlias(): string
    {
        return 'media_default_folder_collection';
    }

    protected function getExpectedClass(): string
    {
        return MediaDefaultFolderEntity::class;
    }
}
