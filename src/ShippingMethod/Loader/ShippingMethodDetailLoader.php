<?php declare(strict_types=1);

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
        if (empty($uuids)) {
            return new ShippingMethodDetailCollection();
        }

        $shippingMethodsCollection = $this->read($uuids, $context);

        $categories = $this->categoryBasicLoader->load($shippingMethodsCollection->getCategoryUuids(), $context);

        $countries = $this->areaCountryBasicLoader->load($shippingMethodsCollection->getCountryUuids(), $context);

        $holidays = $this->holidayBasicLoader->load($shippingMethodsCollection->getHolidayUuids(), $context);

        $paymentMethods = $this->paymentMethodBasicLoader->load($shippingMethodsCollection->getPaymentMethodUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('shipping_method_price.shipping_method_uuid', $uuids));
        /** @var ShippingMethodPriceSearchResult $prices */
        $prices = $this->shippingMethodPriceSearcher->search($criteria, $context);

        /** @var ShippingMethodDetailStruct $shippingMethod */
        foreach ($shippingMethodsCollection as $shippingMethod) {
            $shippingMethod->setCategories($categories->getList($shippingMethod->getCategoryUuids()));
            $shippingMethod->setCountries($countries->getList($shippingMethod->getCountryUuids()));
            $shippingMethod->setHolidays($holidays->getList($shippingMethod->getHolidayUuids()));
            $shippingMethod->setPaymentMethods($paymentMethods->getList($shippingMethod->getPaymentMethodUuids()));
            $shippingMethod->setPrices($prices->filterByShippingMethodUuid($shippingMethod->getUuid()));
        }

        return $shippingMethodsCollection;
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
