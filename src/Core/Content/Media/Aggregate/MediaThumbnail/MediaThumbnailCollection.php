<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaThumbnail;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class MediaThumbnailCollection extends EntityCollection
{
    /**
     * @var MediaThumbnailStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaThumbnailStruct
    {
        return parent::get($id);
    }

    public function current(): MediaThumbnailStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return MediaThumbnailStruct::class;
    }
}
