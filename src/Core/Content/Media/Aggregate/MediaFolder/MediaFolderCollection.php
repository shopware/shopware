<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

/**
 * @package content
 * @extends EntityCollection<MediaFolderEntity>
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
