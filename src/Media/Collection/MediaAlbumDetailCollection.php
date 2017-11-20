<?php declare(strict_types=1);

namespace Shopware\Media\Collection;

use Shopware\Media\Struct\MediaAlbumDetailStruct;

class MediaAlbumDetailCollection extends MediaAlbumBasicCollection
{
    /**
     * @var MediaAlbumDetailStruct[]
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

    public function getMediaUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getMedia()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getMedia(): MediaBasicCollection
    {
        $collection = new MediaBasicCollection();
        foreach ($this->elements as $element) {
            $collection->fill($element->getMedia()->getElements());
        }

        return $collection;
    }

    public function getTranslationUuids(): array
    {
        $uuids = [];
        foreach ($this->elements as $element) {
            foreach ($element->getTranslations()->getUuids() as $uuid) {
                $uuids[] = $uuid;
            }
        }

        return $uuids;
    }

    public function getTranslations(): MediaAlbumTranslationBasicCollection
    {
        $collection = new MediaAlbumTranslationBasicCollection();
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
