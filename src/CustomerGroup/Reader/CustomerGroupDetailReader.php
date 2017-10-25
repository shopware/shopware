<?php declare(strict_types=1);

namespace Shopware\CustomerGroup\Reader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Factory\CustomerGroupDetailFactory;
use Shopware\CustomerGroup\Struct\CustomerGroupDetailCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupDetailStruct;
use Shopware\CustomerGroupDiscount\Searcher\CustomerGroupDiscountSearcher;
use Shopware\CustomerGroupDiscount\Searcher\CustomerGroupDiscountSearchResult;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class CustomerGroupDetailReader
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerGroupDetailFactory
     */
    private $factory;

    /**
     * @var CustomerGroupDiscountSearcher
     */
    private $customerGroupDiscountSearcher;

    public function __construct(
        CustomerGroupDetailFactory $factory,
        CustomerGroupDiscountSearcher $customerGroupDiscountSearcher
    ) {
        $this->factory = $factory;
        $this->customerGroupDiscountSearcher = $customerGroupDiscountSearcher;
    }

    public function readDetail(array $uuids, TranslationContext $context): CustomerGroupDetailCollection
    {
        if (empty($uuids)) {
            return new CustomerGroupDetailCollection();
        }

        $customerGroupsCollection = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('customer_group_discount.customerGroupUuid', $uuids));
        /** @var CustomerGroupDiscountSearchResult $discounts */
        $discounts = $this->customerGroupDiscountSearcher->search($criteria, $context);

        /** @var CustomerGroupDetailStruct $customerGroup */
        foreach ($customerGroupsCollection as $customerGroup) {
            $customerGroup->setDiscounts($discounts->filterByCustomerGroupUuid($customerGroup->getUuid()));
        }

        return $customerGroupsCollection;
    }

    private function read(array $uuids, TranslationContext $context): CustomerGroupDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('customer_group.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CustomerGroupDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CustomerGroupDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
