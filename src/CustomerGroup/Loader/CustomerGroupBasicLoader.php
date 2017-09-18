<?php

namespace Shopware\CustomerGroup\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\CustomerGroup\Factory\CustomerGroupBasicFactory;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicCollection;
use Shopware\CustomerGroup\Struct\CustomerGroupBasicStruct;
use Shopware\Framework\Struct\SortArrayByKeysTrait;

class CustomerGroupBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var CustomerGroupBasicFactory
     */
    private $factory;

    public function __construct(
        CustomerGroupBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): CustomerGroupBasicCollection
    {
        if (empty($uuids)) {
            return new CustomerGroupBasicCollection();
        }

        $customerGroups = $this->read($uuids, $context);

        return $customerGroups;
    }

    private function read(array $uuids, TranslationContext $context): CustomerGroupBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('customer_group.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new CustomerGroupBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new CustomerGroupBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
