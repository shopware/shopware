<?php

namespace Shopware\OrderAddress\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\OrderAddress\Factory\OrderAddressBasicFactory;
use Shopware\OrderAddress\Struct\OrderAddressBasicCollection;
use Shopware\OrderAddress\Struct\OrderAddressBasicStruct;

class OrderAddressBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var OrderAddressBasicFactory
     */
    private $factory;

    public function __construct(
        OrderAddressBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): OrderAddressBasicCollection
    {
        if (empty($uuids)) {
            return new OrderAddressBasicCollection();
        }

        $orderAddressesCollection = $this->read($uuids, $context);

        return $orderAddressesCollection;
    }

    private function read(array $uuids, TranslationContext $context): OrderAddressBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('order_address.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new OrderAddressBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new OrderAddressBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
