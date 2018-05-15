<?php declare(strict_types=1);

namespace Shopware\Content\Media\Collection;

use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Content\Media\Struct\MediaTranslationDetailStruct;

class MediaTranslationDetailCollection extends MediaTranslationBasicCollection
{
    /**
     * @var MediaTranslationDetailStruct[]
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
