<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\ThumbnailSize;

use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;

class ThumbnailSizeCollection extends EntityCollection
{
    public function get(string $id): ? ThumbnailSizeStruct
    {
        return parent::get($id);
    }

    public function current(): ThumbnailSizeStruct
    {
        return parent::current();
    }

    protected function getExpectedClass(): string
    {
        return ThumbnailSizeStruct::class;
    }
}
