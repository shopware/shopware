<?php declare(strict_types=1);

namespace Shopware\Content\Media\Struct;

use Shopware\Content\Media\Collection\MediaAlbumBasicCollection;
use Shopware\Content\Media\Collection\MediaAlbumTranslationBasicCollection;
use Shopware\Content\Media\Collection\MediaBasicCollection;

class MediaAlbumDetailStruct extends MediaAlbumBasicStruct
{
    /**
     * @var MediaAlbumBasicStruct|null
     */
    protected $parent;

    /**
     * @var MediaBasicCollection
     */
    protected $media;

    /**
     * @var MediaAlbumBasicCollection
     */
    protected $children;

    /**
     * @var MediaAlbumTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->media = new MediaBasicCollection();

        $this->children = new MediaAlbumBasicCollection();

        $this->translations = new MediaAlbumTranslationBasicCollection();
    }

    public function getParent(): ?MediaAlbumBasicStruct
    {
        return $this->parent;
    }

    public function setParent(?MediaAlbumBasicStruct $parent): void
    {
        $this->parent = $parent;
    }

    public function getMedia(): MediaBasicCollection
    {
        return $this->media;
    }

    public function setMedia(MediaBasicCollection $media): void
    {
        $this->media = $media;
    }

    public function getChildren(): MediaAlbumBasicCollection
    {
        return $this->children;
    }

    public function setChildren(MediaAlbumBasicCollection $children): void
    {
        $this->children = $children;
    }

    public function getTranslations(): MediaAlbumTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(MediaAlbumTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
