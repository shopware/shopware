<?php declare(strict_types=1);

namespace Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Collection;

use Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationDetailStruct;
use Shopware\Core\System\Currency\Collection\CurrencyBasicCollection;
use Shopware\Core\System\Language\Collection\LanguageBasicCollection;

class CurrencyTranslationDetailCollection extends CurrencyTranslationBasicCollection
{
    /**
     * @var \Shopware\Core\System\Currency\Aggregate\CurrencyTranslation\Struct\CurrencyTranslationDetailStruct[]
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
