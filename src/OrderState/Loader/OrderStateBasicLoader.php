<?php

namespace Shopware\OrderState\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\OrderState\Factory\OrderStateBasicFactory;
use Shopware\OrderState\Struct\OrderStateBasicCollection;
use Shopware\OrderState\Struct\OrderStateBasicStruct;

class OrderStateBasicLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var OrderStateBasicFactory
     */
    private $factory;

    public function __construct(
        OrderStateBasicFactory $factory
    ) {
        $this->factory = $factory;
    }

    public function load(array $uuids, TranslationContext $context): OrderStateBasicCollection
    {
        if (empty($uuids)) {
            return new OrderStateBasicCollection();
        }

        $orderStatesCollection = $this->read($uuids, $context);

        return $orderStatesCollection;
    }

    private function read(array $uuids, TranslationContext $context): OrderStateBasicCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('order_state.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new OrderStateBasicStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new OrderStateBasicCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
