<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaDefaultFolderCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MediaDefaultFolderEntity::class;
    }
}
