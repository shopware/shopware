<?php
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\Shop\Loader;

use Shopware\Context\Struct\TranslationContext;
use Shopware\Currency\Loader\CurrencyBasicLoader;
use Shopware\Locale\Loader\LocaleBasicLoader;
use Shopware\Shop\Reader\ShopDetailReader;
use Shopware\Shop\Struct\ShopDetailCollection;
use Shopware\Shop\Struct\ShopDetailStruct;

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
        ShopDetailReader $reader,
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
            if ($shop->getFallbackLocaleUuid()) {
                $shop->setFallbackLocale($locales->get($shop->getFallbackLocaleUuid()));
            }
            $shop->setCurrencies($currencies->getList($shop->getCurrencyUuids()));
        }

        return $collection;
    }
}
