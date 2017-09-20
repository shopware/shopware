<?php

namespace Shopware\OrderDelivery\Loader;

use Doctrine\DBAL\Connection;
use Shopware\Context\Struct\TranslationContext;
use Shopware\Framework\Struct\SortArrayByKeysTrait;
use Shopware\OrderDelivery\Factory\OrderDeliveryDetailFactory;
use Shopware\OrderDelivery\Struct\OrderDeliveryDetailCollection;
use Shopware\OrderDelivery\Struct\OrderDeliveryDetailStruct;
use Shopware\OrderDeliveryPosition\Searcher\OrderDeliveryPositionSearcher;
use Shopware\OrderDeliveryPosition\Searcher\OrderDeliveryPositionSearchResult;
use Shopware\Search\Criteria;
use Shopware\Search\Query\TermsQuery;

class OrderDeliveryDetailLoader
{
    use SortArrayByKeysTrait;

    /**
     * @var OrderDeliveryDetailFactory
     */
    private $factory;

    /**
     * @var OrderDeliveryPositionSearcher
     */
    private $orderDeliveryPositionSearcher;

    public function __construct(
        OrderDeliveryDetailFactory $factory,
        OrderDeliveryPositionSearcher $orderDeliveryPositionSearcher
    ) {
        $this->factory = $factory;
        $this->orderDeliveryPositionSearcher = $orderDeliveryPositionSearcher;
    }

    public function load(array $uuids, TranslationContext $context): OrderDeliveryDetailCollection
    {
        if (empty($uuids)) {
            return new OrderDeliveryDetailCollection();
        }

        $orderDeliveriesCollection = $this->read($uuids, $context);

        $criteria = new Criteria();
        $criteria->addFilter(new TermsQuery('order_delivery_position.order_delivery_uuid', $uuids));
        /** @var OrderDeliveryPositionSearchResult $positions */
        $positions = $this->orderDeliveryPositionSearcher->search($criteria, $context);

        /** @var OrderDeliveryDetailStruct $orderDelivery */
        foreach ($orderDeliveriesCollection as $orderDelivery) {
            $orderDelivery->setPositions($positions->filterByOrderDeliveryUuid($orderDelivery->getUuid()));
        }

        return $orderDeliveriesCollection;
    }

    private function read(array $uuids, TranslationContext $context): OrderDeliveryDetailCollection
    {
        $query = $this->factory->createQuery($context);

        $query->andWhere('order_delivery.uuid IN (:ids)');
        $query->setParameter(':ids', $uuids, Connection::PARAM_STR_ARRAY);

        $rows = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
        $structs = [];
        foreach ($rows as $row) {
            $struct = $this->factory->hydrate($row, new OrderDeliveryDetailStruct(), $query->getSelection(), $context);
            $structs[$struct->getUuid()] = $struct;
        }

        return new OrderDeliveryDetailCollection(
            $this->sortIndexedArrayByKeys($uuids, $structs)
        );
    }
}
