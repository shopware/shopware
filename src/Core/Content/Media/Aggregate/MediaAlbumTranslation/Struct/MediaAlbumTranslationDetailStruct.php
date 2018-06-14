<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Struct;

use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Struct\MediaAlbumBasicStruct;
use Shopware\Core\System\Language\Struct\LanguageBasicStruct;

class MediaAlbumTranslationDetailStruct extends MediaAlbumTranslationBasicStruct
{
    /**
     * @var MediaAlbumBasicStruct
     */
    protected $mediaAlbum;

    /**
     * @var LanguageBasicStruct
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

    public function getLanguage(): LanguageBasicStruct
    {
        return $this->language;
    }

    public function setLanguage(LanguageBasicStruct $language): void
    {
        $this->language = $language;
    }
}
