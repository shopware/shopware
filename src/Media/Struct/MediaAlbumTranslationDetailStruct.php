<?php declare(strict_types=1);

namespace Shopware\Media\Struct;

use Shopware\Shop\Struct\ShopBasicStruct;

class MediaAlbumTranslationDetailStruct extends MediaAlbumTranslationBasicStruct
{
    /**
     * @var MediaAlbumBasicStruct
     */
    protected $mediaAlbum;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getMediaAlbum(): MediaAlbumBasicStruct
    {
        return $this->mediaAlbum;
    }

    public function setMediaAlbum(MediaAlbumBasicStruct $mediaAlbum): void
    {
        $this->mediaAlbum = $mediaAlbum;
    }

    public function getLanguage(): ShopBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(ShopBasicStruct $language): void
    {
        $this->language = $language;
    }
}
