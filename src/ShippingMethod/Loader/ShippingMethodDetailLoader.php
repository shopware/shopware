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

namespace Shopware\ShippingMethod\Loader;

use Shopware\AreaCountry\Loader\AreaCountryBasicLoader;
use Shopware\Category\Loader\CategoryBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Holiday\Loader\HolidayBasicLoader;
use Shopware\PaymentMethod\Loader\PaymentMethodBasicLoader;
use Shopware\Search\Condition\ShippingMethodUuidCondition;
use Shopware\Search\Criteria;
use Shopware\ShippingMethod\Reader\ShippingMethodDetailReader;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailStruct;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearcher;
use Shopware\ShippingMethodPrice\Struct\ShippingMethodPriceSearchResult;

class ShippingMethodDetailLoader
{
    /**
     * @var ShippingMethodDetailReader
     */
    protected $reader;
    /**
     * @var ShippingMethodPriceSearcher
     */
    private $shippingMethodPriceSearcher;
    /**
     * @var AreaCountryBasicLoader
     */
    private $areaCountryBasicLoader;
    /**
     * @var CategoryBasicLoader
     */
    private $categoryBasicLoader;
    /**
     * @var HolidayBasicLoader
     */
    private $holidayBasicLoader;
    /**
     * @var PaymentMethodBasicLoader
     */
    private $paymentMethodBasicLoader;

    public function __construct(
        ShippingMethodDetailReader $reader,
        ShippingMethodPriceSearcher $shippingMethodPriceSearcher,
        AreaCountryBasicLoader $areaCountryBasicLoader,
        CategoryBasicLoader $categoryBasicLoader,
        HolidayBasicLoader $holidayBasicLoader,
        PaymentMethodBasicLoader $paymentMethodBasicLoader
    ) {
        $this->reader = $reader;
        $this->shippingMethodPriceSearcher = $shippingMethodPriceSearcher;
        $this->areaCountryBasicLoader = $areaCountryBasicLoader;
        $this->categoryBasicLoader = $categoryBasicLoader;
        $this->holidayBasicLoader = $holidayBasicLoader;
        $this->paymentMethodBasicLoader = $paymentMethodBasicLoader;
    }

    public function load(array $uuids, TranslationContext $context): ShippingMethodDetailCollection
    {
        $collection = $this->reader->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addCondition(new ShippingMethodUuidCondition($collection->getUuids()));
        /** @var ShippingMethodPriceSearchResult $shippingMethodPrices */
        $shippingMethodPrices = $this->shippingMethodPriceSearcher->search($criteria, $context);

        $areaCountries = $this->areaCountryBasicLoader->load($collection->getAreaCountryUuids(), $context);
        $categories = $this->categoryBasicLoader->load($collection->getCategoryUuids(), $context);
        $holidaies = $this->holidayBasicLoader->load($collection->getHolidayUuids(), $context);
        $paymentMethods = $this->paymentMethodBasicLoader->load($collection->getPaymentMethodUuids(), $context);

        /** @var ShippingMethodDetailStruct $shippingMethod */
        foreach ($collection as $shippingMethod) {
            $shippingMethod->setShippingMethodPrices(
                $shippingMethodPrices->filterByShippingMethodUuid($shippingMethod->getUuid())
            );
            $shippingMethod->setAreaCountries($areaCountries->getList($shippingMethod->getAreaCountryUuids()));
            $shippingMethod->setCategories($categories->getList($shippingMethod->getCategoryUuids()));
            $shippingMethod->setHolidaies($holidaies->getList($shippingMethod->getHolidayUuids()));
            $shippingMethod->setPaymentMethods($paymentMethods->getList($shippingMethod->getPaymentMethodUuids()));
        }

        return $collection;
    }
}
