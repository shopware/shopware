<?php declare(strict_types=1);

namespace Shopware\System\Currency\Collection;

use Shopware\System\Currency\Struct\CurrencyTranslationDetailStruct;
use Shopware\Api\Language\Collection\LanguageBasicCollection;

class CurrencyTranslationDetailCollection extends CurrencyTranslationBasicCollection
{
    /**
     * @var CurrencyTranslationDetailStruct[]
     */
    protected $elements = [];

    public function getCurrencies(): CurrencyBasicCollection
    {
        return new CurrencyBasicCollection(
            $this->fmap(function (CurrencyTranslationDetailStruct $currencyTranslation) {
                return $currencyTranslation->getCurrency();
            })
        );
    }

    public function getLanguages(): LanguageBasicCollection
    {
        return new LanguageBasicCollection(
            $this->fmap(function (CurrencyTranslationDetailStruct $currencyTranslation) {
                return $currencyTranslation->getLanguage();
            })
        );
    }

    protected function getExpectedClass(): string
    {
        return CurrencyTranslationDetailStruct::class;
    }
}
