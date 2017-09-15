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

use Doctrine\DBAL\Connection;
use Shopware\AreaCountry\Loader\AreaCountryBasicLoader;
use Shopware\Category\Loader\CategoryBasicLoader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Holiday\Loader\HolidayBasicLoader;
use Shopware\PaymentMethod\Loader\PaymentMethodBasicLoader;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;
use Shopware\ShippingMethod\Factory\ShippingMethodDetailFactory;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailStruct;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearcher;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearchResult;

class ShippingMethodDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var ShippingMethodDetailFactory
     */
    private $factory;

    /**
     * @var CategoryBasicLoader
     */
    private $categoryBasicLoader;

    /**
     * @var AreaCountryBasicLoader
     */
    private $areaCountryBasicLoader;

    /**
     * @var HolidayBasicLoader
     */
    private $holidayBasicLoader;

    /**
     * @var PaymentMethodBasicLoader
     */
    private $paymentMethodBasicLoader;

    /**
     * @var ShippingMethodPriceSearcher
     */
    private $shippingMethodPriceSearcher;

    public function __construct(
        ShippingMethodDetailFactory $factory,
CategoryBasicLoader $categoryBasicLoader,
AreaCountryBasicLoader $areaCountryBasicLoader,
HolidayBasicLoader $holidayBasicLoader,
PaymentMethodBasicLoader $paymentMethodBasicLoader,
ShippingMethodPriceSearcher $shippingMethodPriceSearcher
    ) {
        $this->factory = $factory;
        $this->categoryBasicLoader = $categoryBasicLoader;
        $this->areaCountryBasicLoader = $areaCountryBasicLoader;
        $this->holidayBasicLoader = $holidayBasicLoader;
        $this->paymentMethodBasicLoader = $paymentMethodBasicLoader;
        $this->shippingMethodPriceSearcher = $shippingMethodPriceSearcher;
    }

    public function load(array $uuids, TranslationContext $context): ShippingMethodDetailCollection
    {
        $shippingMethods = $this->read($uuids, $context);

        $categories = $this->categoryBasicLoader->load($shippingMethods->getCategoryUuids(), $context);

        $countries = $this->areaCountryBasicLoader->load($shippingMethods->getCountryUuids(), $context);

        $holidaies = $this->holidayBasicLoader->load($shippingMethods->getHolidayUuids(), $context);

        $paymentMethods = $this->paymentMethodBasicLoader->load($shippingMethods->getPaymentMethodUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('shipping_method_price.shipping_method_uuid', $uuids));
        /** @var ShippingMethodPriceSearchResult $prices */
        $prices = $this->shippingMethodPriceSearcher->search($criteria, $context);

        /** @var ShippingMethodDetailStruct $shippingMethod */
        foreach ($shippingMethods as $shippingMethod) {
            $shippingMethod->setCategories($categories->getList($shippingMethod->getCategoryUuids()));
            $shippingMethod->setCountries($countries->getList($shippingMethod->getCountryUuids()));
            $shippingMethod->setHolidaies($holidaies->getList($shippingMethod->getHolidayUuids()));
            $shippingMethod->setPaymentMethods($paymentMethods->getList($shippingMethod->getPaymentMethodUuids()));
            $shippingMethod->setPrices($prices->filterByShippingMethodUuid($shippingMethod->getUuid()));
        }

        return $shippingMethods;
    }

    private function read(array $uuids, TranslationContext $context): ShippingMethodDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('shipping_method.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new ShippingMethodDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new ShippingMethodDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
