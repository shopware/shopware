<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaThumbnailSizeCollection extends EntityCollection
{
    public function get(string $id): ? MediaThumbnailSizeStruct
    {
        return parent::get($id);
    }

    public function current(): MediaThumbnailSizeStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailSizeStruct::class;
    }
}
