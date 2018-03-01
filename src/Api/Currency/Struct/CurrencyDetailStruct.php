<?php declare(strict_types=1);

namespace Shopware\Api\Currency\Struct;

use Shopware\Api\Currency\Collection\CurrencyTranslationBasicCollection;
use Shopware\Api\Order\Collection\OrderBasicCollection;
use Shopware\Api\Shop\Collection\ShopBasicCollection;

class CurrencyDetailStruct extends CurrencyBasicStruct
{
    /**
     * @var CurrencyTranslationBasicCollection
     */
    protected $translations;

    public function __construct()
    {
        $this->translations = new CurrencyTranslationBasicCollection();
    }

    public function getTranslations(): CurrencyTranslationBasicCollection
    {
        return $this->translations;
    }

    public function setTranslations(CurrencyTranslationBasicCollection $translations): void
    {
        $this->translations = $translations;
    }
}
