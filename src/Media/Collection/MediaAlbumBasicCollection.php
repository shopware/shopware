<?php declare(strict_types=1);

namespace Shopware\Media\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Media\Struct\MediaAlbumBasicStruct;

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

    public function filterByParentUuid(string $uuid): MediaAlbumBasicCollection
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
