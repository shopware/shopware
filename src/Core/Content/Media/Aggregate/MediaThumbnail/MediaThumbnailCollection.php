<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaThumbnailCollection extends EntityCollection
{
    /**
     * @var MediaThumbnailEntity[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaThumbnailEntity
    {
        return parent::get($id);
    }

    public function current(): MediaThumbnailEntity
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailEntity::class;
    }
}
