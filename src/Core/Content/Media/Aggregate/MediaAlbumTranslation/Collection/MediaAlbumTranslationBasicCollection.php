<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Collection;

use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Struct\MediaAlbumTranslationBasicStruct;
use Shopware\Core\Framework\ORM\EntityCollection;

class MediaAlbumTranslationBasicCollection extends EntityCollection
{
    /**
     * @var MediaAlbumTranslationBasicStruct[]
     */
    protected $elements = [];

    public function get(string $id): ? MediaAlbumTranslationBasicStruct
    {
        return parent::get($id);
    }

    public function current(): MediaAlbumTranslationBasicStruct
    {
        return parent::current();
    }

    public function getMediaAlbumIds(): array
    {
        return $this->fmap(function (MediaAlbumTranslationBasicStruct $mediaAlbumTranslation) {
            return $mediaAlbumTranslation->getMediaAlbumId();
        });
    }

    public function filterByMediaAlbumId(string $id): self
    {
        return $this->filter(function (MediaAlbumTranslationBasicStruct $mediaAlbumTranslation) use ($id) {
            return $mediaAlbumTranslation->getMediaAlbumId() === $id;
        });
    }

    public function getLanguageIds(): array
    {
        return $this->fmap(function (MediaAlbumTranslationBasicStruct $mediaAlbumTranslation) {
            return $mediaAlbumTranslation->getLanguageId();
        });
    }

    public function filterByLanguageId(string $id): self
    {
        return $this->filter(function (MediaAlbumTranslationBasicStruct $mediaAlbumTranslation) use ($id) {
            return $mediaAlbumTranslation->getLanguageId() === $id;
        });
    }

    protected function getExpectedClass(): string
    {
        return MediaAlbumTranslationBasicStruct::class;
    }
}
