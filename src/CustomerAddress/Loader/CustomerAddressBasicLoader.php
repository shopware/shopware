<?php

namespace Shopware\CustomerAddress\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerAddress\Factory\CustomerAddressBasicFactory;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicCollection;
use Shopware\CustomerAddress\Struct\CustomerAddressBasicStruct;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CustomerAddressBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerAddressBasicFactory
     */
    private $factory;

    public function __construct(
        CustomerAddressBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): CustomerAddressBasicCollection
    {
        if (empty($uuids)) {
            return new CustomerAddressBasicCollection();
        }

        $customerAddresss = $this->read($uuids, $context);

        return $customerAddresss;
    }

    private function read(array $uuids, TranslationContext $context): CustomerAddressBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('customer_address.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CustomerAddressBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CustomerAddressBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
