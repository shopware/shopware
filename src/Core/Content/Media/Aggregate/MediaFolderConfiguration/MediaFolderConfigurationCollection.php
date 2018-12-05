<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaFolderConfiguration;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaFolderConfigurationCollection extends EntityCollection
{
    public function get(string $id): ? MediaFolderConfigurationStruct
    {
        return parent::get($id);
    }

    public function current(): MediaFolderConfigurationStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return MediaFolderConfigurationStruct::class;
    }
}
