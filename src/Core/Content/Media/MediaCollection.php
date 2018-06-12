<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media;

use Shopware\Core\Content\Media\Aggregate\MediaAlbum\MediaAlbumCollection;
use Shopware\Core\Framework\ORM\EntityCollection;

class MediaCollection extends EntityCollection
{
    /**
     * @var MediaStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaStruct
    {
        return parent::get($id);
    }

    public function current(): MediaStruct
    {
        return parent::current();
    }

    public function getAlbumIds(): array
    {
        return $this->fmap(function (MediaStruct $media) {
            return $media->getAlbumId();
        });
    }

    public function filterByAlbumId(string $id): self
    {
        return $this->filter(function (MediaStruct $media) use ($id) {
            return $media->getAlbumId() === $id;
        });
    }

    public function getUserIds(): array
    {
        return $this->fmap(function (MediaStruct $media) {
            return $media->getUserId();
        });
    }

    public function filterByUserId(string $id): self
    {
        return $this->filter(function (MediaStruct $media) use ($id) {
            return $media->getUserId() === $id;
        });
    }

    public function getAlbums(): MediaAlbumCollection
    {
        return new MediaAlbumCollection(
            $this->fmap(function (MediaStruct $media) {
                return $media->getAlbum();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return MediaStruct::class;
    }
}
