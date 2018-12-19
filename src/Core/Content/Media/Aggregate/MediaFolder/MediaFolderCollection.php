<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaFolderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MediaFolderEntity::class;
    }
}
