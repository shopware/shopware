<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaThumbnailSizeCollection extends EntityCollection
{
    public function get(string $id): ? MediaThumbnailSizeEntity
    {
        return parent::get($id);
    }

    public function current(): MediaThumbnailSizeEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailSizeEntity::class;
    }
}
