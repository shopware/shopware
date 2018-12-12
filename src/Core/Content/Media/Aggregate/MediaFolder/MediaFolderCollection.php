<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaFolderCollection extends EntityCollection
{
    public function get(string $id): ? MediaFolderEntity
    {
        return parent::get($id);
    }

    public function current(): MediaFolderEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return MediaFolderEntity::class;
    }
}
