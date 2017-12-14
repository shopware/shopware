<?php declare(strict_types=1);

namespace Shopware\Api\Media\Collection;

use Shopware\Api\Media\Struct\MediaTranslationDetailStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

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

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
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
