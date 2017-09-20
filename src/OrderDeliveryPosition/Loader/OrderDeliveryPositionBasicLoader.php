<?php

namespace Shopware\OrderDeliveryPosition\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\OrderDeliveryPosition\Factory\OrderDeliveryPositionBasicFactory;
use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicCollection;
use Shopware\OrderDeliveryPosition\Struct\OrderDeliveryPositionBasicStruct;

class OrderDeliveryPositionBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var OrderDeliveryPositionBasicFactory
     */
    private $factory;

    public function __construct(
        OrderDeliveryPositionBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): OrderDeliveryPositionBasicCollection
    {
        if (empty($uuids)) {
            return new OrderDeliveryPositionBasicCollection();
        }

        $orderDeliveryPositionsCollection = $this->read($uuids, $context);

        return $orderDeliveryPositionsCollection;
    }

    private function read(array $uuids, TranslationContext $context): OrderDeliveryPositionBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('order_delivery_position.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new OrderDeliveryPositionBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new OrderDeliveryPositionBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
