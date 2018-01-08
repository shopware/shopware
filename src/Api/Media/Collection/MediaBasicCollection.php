<?php declare(strict_types=1);

namespace Shopware\Api\Media\Collection;

use Shopware\Api\Entity\EntityCollection;
use Shopware\Api\Media\Struct\MediaBasicStruct;

class MediaBasicCollection extends EntityCollection
{
    /**
     * @var MediaBasicStruct[]
     */
    protected $elements = [];

    public function get(string $uuid): ? MediaBasicStruct
    {
        return parent::get($uuid);
    }

    public function current(): MediaBasicStruct
    {
        return parent::current();
    }

    public function getAlbumUuids(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getAlbumUuid();
        });
    }

    public function filterByAlbumUuid(string $uuid): self
    {
        return $this->filter(function (MediaBasicStruct $media) use ($uuid) {
            return $media->getAlbumUuid() === $uuid;
        });
    }

    public function getUserUuids(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getUserUuid();
        });
    }

    public function filterByUserUuid(string $uuid): self
    {
        return $this->filter(function (MediaBasicStruct $media) use ($uuid) {
            return $media->getUserUuid() === $uuid;
        });
    }

    public function getAlbums(): MediaAlbumBasicCollection
    {
        return new MediaAlbumBasicCollection(
            $this->fmap(function (MediaBasicStruct $media) {
                return $media->getAlbum();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return MediaBasicStruct::class;
    }
}
