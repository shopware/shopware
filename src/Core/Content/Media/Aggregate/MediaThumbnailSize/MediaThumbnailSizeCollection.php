<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnailSize;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaThumbnailSizeCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MediaThumbnailSizeEntity::class;
    }
}
