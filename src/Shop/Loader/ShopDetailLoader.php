<?php

namespace Shopware\Shop\Loader;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Shop\Reader\ShopDetailReader;
use Shopware\Shop\Struct\ShopDetailStruct;
use Shopware\Shop\Struct\ShopDetailCollection;
use Shopware\Locale\Loader\LocaleBasicLoader;
use Shopware\Currency\Loader\CurrencyBasicLoader;

class ShopDetailLoader
{
    /**
     * @var ShopDetailReader
     */
    protected $reader;
    /**
     * @var LocaleBasicLoader
     */
    private $localeBasicLoader;
    /**
     * @var CurrencyBasicLoader
     */
    private $currencyBasicLoader;

    public function __construct(
        ShopDetailReader $reader
        ,
        LocaleBasicLoader $localeBasicLoader,
        CurrencyBasicLoader $currencyBasicLoader
    ) {
        $this->reader = $reader;
        $this->localeBasicLoader = $localeBasicLoader;
        $this->currencyBasicLoader = $currencyBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): ShopDetailCollection
    {
        $collection = $this->reader->read($uuids, $context);
        $locales = $this->localeBasicLoader->load($collection->getFallbackLocaleUuids(), $context);
        $currencies = $this->currencyBasicLoader->load($collection->getCurrencyUuids(), $context);

        /** @var ShopDetailStruct $shop */
        foreach ($collection as $shop) {
            if ($shop->getFallbackLocaleUuid())) {
                $shop->setFallbackLocale($locales->get($shop->getFallbackLocaleUuid()));
            }
            $shop->setCurrencies($currencies->getList($shop->getCurrencyUuids()));
        }

        return $collection;
    }
}