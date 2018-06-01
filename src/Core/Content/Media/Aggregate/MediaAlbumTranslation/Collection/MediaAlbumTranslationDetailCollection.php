<?php declare(strict_types=1);

namespace Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Collection;

use Shopware\Core\System\Language\Collection\LanguageBasicCollection;
use Shopware\Core\Content\Media\Aggregate\MediaAlbum\Collection\MediaAlbumBasicCollection;
use Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Struct\MediaAlbumTranslationDetailStruct;

class MediaAlbumTranslationDetailCollection extends MediaAlbumTranslationBasicCollection
{
    /**
     * @var \Shopware\Core\Content\Media\Aggregate\MediaAlbumTranslation\Struct\MediaAlbumTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getMediaAlbum(): MediaAlbumBasicCollection
    {
        return new MediaAlbumBasicCollection(
            $this->fmap(function (MediaAlbumTranslationDetailStruct $mediaAlbumTranslation) {
                return $mediaAlbumTranslation->getMediaAlbum();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (MediaAlbumTranslationDetailStruct $mediaAlbumTranslation) {
                return $mediaAlbumTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return MediaAlbumTranslationDetailStruct::class;
    }
}
