<?php declare(strict_types=1);

namespace Shopware\CustomerGroupDiscount\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroupDiscount\Factory\CustomerGroupDiscountBasicFactory;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicCollection;
use Shopware\CustomerGroupDiscount\Struct\CustomerGroupDiscountBasicStruct;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CustomerGroupDiscountBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerGroupDiscountBasicFactory
     */
    private $factory;

    public function __construct(
        CustomerGroupDiscountBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): CustomerGroupDiscountBasicCollection
    {
        if (empty($uuids)) {
            return new CustomerGroupDiscountBasicCollection();
        }

        $customerGroupDiscountsCollection = $this->read($uuids, $context);

        return $customerGroupDiscountsCollection;
    }

    private function read(array $uuids, TranslationContext $context): CustomerGroupDiscountBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('customer_group_discount.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CustomerGroupDiscountBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CustomerGroupDiscountBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
