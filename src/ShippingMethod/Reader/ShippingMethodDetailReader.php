<?php declare(strict_types=1);

namespace Shopware\ShippingMethod\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Api\Read\DetailReaderInterface;
use Shopware\Api\Search\Criteria;
use Shopware\Api\Search\Query\TermsQuery;
use Shopware\AreaCountry\Reader\AreaCountryBasicReader;
use Shopware\Category\Reader\CategoryBasicReader;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Holiday\Reader\HolidayBasicReader;
use Shopware\PaymentMethod\Reader\PaymentMethodBasicReader;
use Shopware\ShippingMethod\Factory\ShippingMethodDetailFactory;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailCollection;
use Shopware\ShippingMethod\Struct\ShippingMethodDetailStruct;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearcher;
use Shopware\ShippingMethodPrice\Searcher\ShippingMethodPriceSearchResult;

class ShippingMethodDetailReader implements DetailReaderInterface
{
    use SortArrayByKeysTrait;

    /**
     * @var ShippingMethodDetailFactory
     */
    private $factory;

    /**
     * @var CategoryBasicReader
     */
    private $categoryBasicReader;

    /**
     * @var AreaCountryBasicReader
     */
    private $areaCountryBasicReader;

    /**
     * @var HolidayBasicReader
     */
    private $holidayBasicReader;

    /**
     * @var PaymentMethodBasicReader
     */
    private $paymentMethodBasicReader;

    /**
     * @var ShippingMethodPriceSearcher
     */
    private $shippingMethodPriceSearcher;

    public function __construct(
        ShippingMethodDetailFactory $factory,
        CategoryBasicReader $categoryBasicReader,
        AreaCountryBasicReader $areaCountryBasicReader,
        HolidayBasicReader $holidayBasicReader,
        PaymentMethodBasicReader $paymentMethodBasicReader,
        ShippingMethodPriceSearcher $shippingMethodPriceSearcher
    ) {
        $this->factory = $factory;
        $this->categoryBasicReader = $categoryBasicReader;
        $this->areaCountryBasicReader = $areaCountryBasicReader;
        $this->holidayBasicReader = $holidayBasicReader;
        $this->paymentMethodBasicReader = $paymentMethodBasicReader;
        $this->shippingMethodPriceSearcher = $shippingMethodPriceSearcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): ShippingMethodDetailCollection
    {
        if (empty($uuids)) {
            return new ShippingMethodDetailCollection();
        }

        $shippingMethodsCollection = $this->read($uuids, $context);

        $categories = $this->categoryBasicReader->readBasic($shippingMethodsCollection->getCategoryUuids(), $context);

        $countries = $this->areaCountryBasicReader->readBasic($shippingMethodsCollection->getCountryUuids(), $context);

        $holidays = $this->holidayBasicReader->readBasic($shippingMethodsCollection->getHolidayUuids(), $context);

        $paymentMethods = $this->paymentMethodBasicReader->readBasic($shippingMethodsCollection->getPaymentMethodUuids(), $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('shipping_method_price.shippingMethodUuid', $uuids));
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
