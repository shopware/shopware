<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Collection;

use Shopware\Api\Locale\Struct\LocaleTranslationDetailStruct;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class LocaleTranslationDetailCollection extends LocaleTranslationBasicCollection
{
    /**
     * @var LocaleTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getLocales(): LocaleBasicCollection
    {
        return new LocaleBasicCollection(
            $this->fmap(function (LocaleTranslationDetailStruct $localeTranslation) {
                return $localeTranslation->getLocale();
            })
        );
    }

    public function getLanguages(): ShopBasicCollection
    {
        return new ShopBasicCollection(
            $this->fmap(function (LocaleTranslationDetailStruct $localeTranslation) {
                return $localeTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return LocaleTranslationDetailStruct::class;
    }
}
