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

    public function get(string $uuid): ? MediaAlbumBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): MediaAlbumBasicStruct
    {
        return parent::current();
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (MediaAlbumBasicStruct $mediaAlbum) {
            return $mediaAlbum->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): self
    {
        return $this->filter(function (MediaAlbumBasicStruct $mediaAlbum) use ($uuid) {
            return $mediaAlbum->getParentUuid() === $uuid;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaAlbumBasicStruct::class;
    }
}
