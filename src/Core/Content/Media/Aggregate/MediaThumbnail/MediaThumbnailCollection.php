<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaThumbnailCollection extends EntityCollection
{
    protected function getExpectedClass(): string
    {
        return MediaThumbnailEntity::class;
    }
}
