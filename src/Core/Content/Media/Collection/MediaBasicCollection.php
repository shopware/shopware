<?php declare(strict_types=1);

namespace Shopware\Content\Media\Collection;

use Shopware\Framework\ORM\EntityCollection;
use Shopware\Content\Media\Struct\MediaBasicStruct;

class MediaBasicCollection extends EntityCollection
{
    /**
     * @var MediaBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaBasicStruct
    {
        return parent::get($id);
    }

    public function current(): MediaBasicStruct
    {
        return parent::current();
    }

    public function getAlbumIds(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getAlbumId();
        });
    }

    public function filterByAlbumId(string $id): self
    {
        return $this->filter(function (MediaBasicStruct $media) use ($id) {
            return $media->getAlbumId() === $id;
        });
    }

    public function getUserIds(): array
    {
        return $this->fmap(function (MediaBasicStruct $media) {
            return $media->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (MediaBasicStruct $media) use ($id) {
            return $media->getUserId() === $id;
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
