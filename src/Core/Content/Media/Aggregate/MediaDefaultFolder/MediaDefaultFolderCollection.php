<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaDefaultFolder;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaDefaultFolderCollection extends EntityCollection
{
    public function get(string $id): ? MediaDefaultFolderStruct
    {
        return parent::get($id);
    }

    public function current(): MediaDefaultFolderStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return MediaDefaultFolderStruct::class;
    }
}
