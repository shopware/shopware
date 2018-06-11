<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbum\Collection;

use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumDetailStruct;
use Shopware\Core\Content\Media\Collection\MediaBasicCollection;

class MediaAlbumDetailCollection extends MediaAlbumBasicCollection
{
    /**
     * @var \Shopware\Core\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumDetailStruct[]
     */
    protected $elements = [];

    public function getParents(): MediaAlbumBasicCollection
    {
        return new MediaAlbumBasicCollection(
            $this->fmap(function (MediaAlbumDetailStruct $mediaAlbum) {
                return $mediaAlbum->getParent();
            })
        );
    }

    public function getMediaIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMedia()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getMedia(): MediaBasicCollection
    {
        $collection = new MediaBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMedia()->getElements());
        }

        return $collection;
    }

    public function getChildrenIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getChildren()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getChildren(): MediaAlbumBasicCollection
    {
        $collection = new MediaAlbumBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getChildren()->getElements());
        }

        return $collection;
    }

    public function getTranslationIds(): array
    {
        $ids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getIds() as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }

    public function getTranslations(): \Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Collection\MediaAlbumTranslationBasicCollection
    {
        $collection = new \Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Collection\MediaAlbumTranslationBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getTranslations()->getElements());
        }

        return $collection;
    }

    protected function getExpectedClass(): string
    {
        return MediaAlbumDetailStruct::class;
    }
}
