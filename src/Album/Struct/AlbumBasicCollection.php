<?php declare(strict_types=1);

namespace Shopware\Album\Struct;

use Shopware\Framework\Struct\Collection;

class AlbumBasicCollection extends Collection
{
    /**
     * @var AlbumBasicStruct[]
     */
    protected $elements = [];

    public function add(AlbumBasicStruct $album): void
    {
        $key = $this->getKey($album);
        $this->elements[$key] = $album;
    }

    public function remove(string $uuid): void
    {
        parent::doRemoveByKey($uuid);
    }

    public function removeElement(AlbumBasicStruct $album): void
    {
        parent::doRemoveByKey($this->getKey($album));
    }

    public function exists(AlbumBasicStruct $album): bool
    {
        return parent::has($this->getKey($album));
    }

    public function getList(array $uuids): AlbumBasicCollection
    {
        return new self(array_intersect_key($this->elements, array_flip($uuids)));
    }

    public function get(string $uuid): ? AlbumBasicStruct
    {
        if ($this->has($uuid)) {
            return $this->elements[$uuid];
        }

        return null;
    }

    public function getUuids(): array
    {
        return $this->fmap(function (AlbumBasicStruct $album) {
            return $album->getUuid();
        });
    }

    public function merge(AlbumBasicCollection $collection)
    {
        /** @var AlbumBasicStruct $album */
        foreach ($collection as $album) {
            if ($this->has($this->getKey($album))) {
                continue;
            }
            $this->add($album);
        }
    }

    public function getParentUuids(): array
    {
        return $this->fmap(function (AlbumBasicStruct $album) {
            return $album->getParentUuid();
        });
    }

    public function filterByParentUuid(string $uuid): AlbumBasicCollection
    {
        return $this->filter(function (AlbumBasicStruct $album) use ($uuid) {
            return $album->getParentUuid() === $uuid;
        });
    }

    public function current(): AlbumBasicStruct
    {
        return parent::current();
    }

    protected function getKey(AlbumBasicStruct $element): string
    {
        return $element->getUuid();
    }
}
