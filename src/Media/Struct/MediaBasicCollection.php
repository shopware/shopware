<?php declare(strict_types=1);

namespace Shopware\Media\Struct;

use Shopware\Album\Struct\AlbumBasicCollection;
use Shopware\Framework\Struct\Collection;

class MediaBasicCollection extends Collection
{
    /**
     * @var MediaBasicStruct[]
     */
    protected $elements = [];

    public function add(MediaBasicStruct $media): void
    {
        $key = $this->getKey($media);
        $this->elements[$key] = $media;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(MediaBasicStruct $media): void
    {
        parent::doRemoveByKey($this->getKey($media));
    }

    public function exists(MediaBasicStruct $media): bool
    {
        return parent::has($this->getKey($media));
    }

    public function getList(array $uuids): MediaBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? MediaBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getUuid();
        });
    }

    public function merge(MediaBasicCollection $collection)
    {
        /** @var MediaBasicStruct $media */
        foreach ($collection as $media) {
            if ($this->has($this->getKey($media))) {
                continue;
            }
            $this->add($media);
        }
    }

    public function getAlbumUuids(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getAlbumUuid();
        });
    }

    public function filterByAlbumUuid(string $uuid): MediaBasicCollection
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

    public function filterByUserUuid(string $uuid): MediaBasicCollection
    {
        return $this->filter(function (MediaBasicStruct $media) use ($uuid) {
            return $media->getUserUuid() === $uuid;
        });
    }

    public function getAlbum(): AlbumBasicCollection
    {
        return new AlbumBasicCollection(
            $this->fmap(function (MediaBasicStruct $media) {
                return $media->getAlbum();
            })
        );
    }

    protected function getKey(MediaBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
