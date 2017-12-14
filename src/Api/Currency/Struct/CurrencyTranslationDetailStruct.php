<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Struct;

use Shopware\Api\Shop\Struct\ShopBasicStruct;

class CurrencyTranslationDetailStruct extends CurrencyTranslationBasicStruct
{
    /**
     * @var CurrencyBasicStruct
     */
    protected $currency;

    /**
     * @var ShopBasicStruct
     */
    protected $language;

    public function getCurrency(): CurrencyBasicStruct
    {
        return $this->currency;
    }

    public function setCurrency(CurrencyBasicStruct $currency): void
    {
        $this->currency = $currency;
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
