<?php declare(strict_types=1);

namespace Shopware\Content\Media\Aggregate\MediaTranslation\Collection;

use Shopware\System\Language\Collection\LanguageBasicCollection;
use Shopware\Content\Media\Aggregate\MediaTranslation\Struct\MediaTranslationDetailStruct;
use Shopware\Content\Media\Collection\MediaBasicCollection;

class MediaTranslationDetailCollection extends MediaTranslationBasicCollection
{
    /**
     * @var \Shopware\Content\Media\Aggregate\MediaTranslation\Struct\MediaTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getMedia(): MediaBasicCollection
    {
        return new MediaBasicCollection(
            $this->fmap(function (MediaTranslationDetailStruct $mediaTranslation) {
                return $mediaTranslation->getMedia();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (MediaTranslationDetailStruct $mediaTranslation) {
                return $mediaTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return MediaTranslationDetailStruct::class;
    }
}
