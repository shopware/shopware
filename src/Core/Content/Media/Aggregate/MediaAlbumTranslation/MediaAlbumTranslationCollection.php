<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation;

use Shopware\Core\Framework\ORM\EntityCollection;

class MediaAlbumTranslationCollection extends EntityCollection
{
    /**
     * @var MediaAlbumTranslationStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaAlbumTranslationStruct
    {
        return parent::get($id);
    }

    public function current(): MediaAlbumTranslationStruct
    {
        return parent::current();
    }

    public function getMediaAlbumIds(): array
    {
        return $this->fmap(function (MediaAlbumTranslationStruct $mediaAlbumTranslation) {
            return $mediaAlbumTranslation->getMediaAlbumId();
        });
    }

    public function filterByMediaAlbumId(string $id): self
    {
        return $this->filter(function (MediaAlbumTranslationStruct $mediaAlbumTranslation) use ($id) {
            return $mediaAlbumTranslation->getMediaAlbumId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (MediaAlbumTranslationStruct $mediaAlbumTranslation) {
            return $mediaAlbumTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MediaAlbumTranslationStruct $mediaAlbumTranslation) use ($id) {
            return $mediaAlbumTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaAlbumTranslationStruct::class;
    }
}
