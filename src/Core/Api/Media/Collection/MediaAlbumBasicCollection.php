<?php declare(strict_types=1);

namespace Shopware\Api\Media\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Media\Struct\MediaAlbumBasicStruct;

class MediaAlbumBasicCollection extends EntityCollection
{
    /**
     * @var MediaAlbumBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaAlbumBasicStruct
    {
        return parent::get($id);
    }

    public function current(): MediaAlbumBasicStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (MediaAlbumBasicStruct $mediaAlbum) {
            return $mediaAlbum->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (MediaAlbumBasicStruct $mediaAlbum) use ($id) {
            return $mediaAlbum->getParentId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaAlbumBasicStruct::class;
    }
}
