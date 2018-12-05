<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaFolderCollection extends EntityCollection
{
    public function get(string $id): ? MediaFolderStruct
    {
        return parent::get($id);
    }

    public function current(): MediaFolderStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return MediaFolderStruct::class;
    }
}
