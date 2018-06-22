<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbum;

use Shopware\Core\Framework\ORM\EntityCollection;

class MediaAlbumCollection extends EntityCollection
{
    /**
     * @var MediaAlbumStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaAlbumStruct
    {
        return parent::get($id);
    }

    public function current(): MediaAlbumStruct
    {
        return parent::current();
    }

    public function getParentIds(): array
    {
        return $this->fmap(function (MediaAlbumStruct $mediaAlbum) {
            return $mediaAlbum->getParentId();
        });
    }

    public function filterByParentId(string $id): self
    {
        return $this->filter(function (MediaAlbumStruct $mediaAlbum) use ($id) {
            return $mediaAlbum->getParentId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaAlbumStruct::class;
    }
}
