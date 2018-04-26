<?php declare(strict_types=1);

namespace Shopware\Api\Locale\Collection;

use Shopware\Api\Language\Collection\LanguageBasicCollection;
use Shopware\Api\Locale\Struct\LocaleTranslationDetailStruct;

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

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
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
