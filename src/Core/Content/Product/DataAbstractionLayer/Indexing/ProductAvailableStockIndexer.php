<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\Service\ProductAvailableStockCalculationService;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\LastIdQuery;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Indexing\IndexerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class ProductAvailableStockIndexer implements IndexerInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var ProductAvailableStockCalculationService
     */
    private $productAvailableStockCalculationService;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    public function __construct(
        Connection $connection,
        ProductAvailableStockCalculationService $productAvailableStockCalculationService,
        EventDispatcherInterface $eventDispatcher
    ) {
        $this->connection = $connection;
        $this->productAvailableStockCalculationService = $productAvailableStockCalculationService;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();
        $productIterator = $this->createIterator();

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing available stocks of products', $productIterator->fetchCount())
        );

        while ($binaryIds = $productIterator->fetch()) {
            $ids = array_map(function ($binaryId) {
                return Uuid::fromBytesToHex($binaryId);
            }, $binaryIds);

            $this->productAvailableStockCalculationService->recalculate($ids, $context);

            $this->eventDispatcher->dispatch(
                ProgressAdvancedEvent::NAME,
                new ProgressAdvancedEvent(\count($ids))
            );
        }

        $this->eventDispatcher->dispatch(
            ProgressFinishedEvent::NAME,
            new ProgressFinishedEvent('Finished indexing available stocks of products')
        );
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $this->productAvailableStockCalculationService->recalculate(
            $this->getIdsOfAffectedProducts($event),
            $event->getContext()
        );
    }

    private function getIdsOfAffectedProducts(EntityWrittenContainerEvent $event): array
    {
        $productIds = [];

        $productWrittenEvent = $event->getEventByDefinition(ProductDefinition::class);
        if ($productWrittenEvent) {
            $productIds = $productWrittenEvent->getIds();
        }

        // Collect product IDs affected by any changed of order deliveries or order delivery positions
        return array_unique(
            array_merge(
                $productIds,
                $this->collectProductIdsFromWrittenOrderDeliveries($event),
                $this->collectProductIdsFromWrittenOrderDeliveryPositions($event)
            )
        );
    }

    private function collectProductIdsFromWrittenOrderDeliveries(EntityWrittenContainerEvent $event): array
    {
        $productIds = [];

        $orderDeliveryWrittenEvent = $event->getEventByDefinition(OrderDeliveryDefinition::class);
        if ($orderDeliveryWrittenEvent) {
            $sql = <<<SQL
SELECT 
    TRIM(BOTH '"' FROM JSON_EXTRACT(`order_line_item`.`payload`, '$.id')) AS `product_id`
FROM `order_delivery`
LEFT JOIN `order_delivery_position`
    ON `order_delivery_position`.`order_delivery_id` = `order_delivery`.`id`
LEFT JOIN `order_line_item`
    ON `order_line_item`.`id` = `order_delivery_position`.`order_line_item_id`
WHERE
    `order_delivery`.`id` IN (:ids)
    AND `order_line_item`.`type` = 'product'
SQL;

            $productIdsRelatedToOrderDeliveries = $this->connection->fetchAll(
                $sql,
                ['ids' => array_map([Uuid::class, 'fromHexToBytes'], $orderDeliveryWrittenEvent->getIds())],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
            $productIds = array_column($productIdsRelatedToOrderDeliveries, 'product_id');
        }

        return $productIds;
    }

    private function collectProductIdsFromWrittenOrderDeliveryPositions(EntityWrittenContainerEvent $event): array
    {
        $productIds = [];

        $orderDeliveryPositionWrittenEvent = $event->getEventByDefinition(OrderDeliveryPositionDefinition::class);
        if ($orderDeliveryPositionWrittenEvent) {
            $sql = <<<SQL
SELECT 
    TRIM(BOTH '"' FROM JSON_EXTRACT(`order_line_item`.`payload`, '$.id')) AS `product_id`
FROM `order_delivery_position`
LEFT JOIN `order_line_item`
    ON `order_line_item`.`id` = `order_delivery_position`.`order_line_item_id`
WHERE
    `order_delivery_position`.`id` IN (:ids)
    AND `order_line_item`.`type` = 'product'
SQL;

            $productIdsRelatedToOrderDeliveryPositiosn = $this->connection->fetchAll(
                $sql,
                ['ids' => array_map([Uuid::class, 'fromHexToBytes'], $orderDeliveryPositionWrittenEvent->getIds())],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
            $productIds = array_column($productIdsRelatedToOrderDeliveryPositiosn, 'product_id');
        }

        return $productIds;
    }

    private function createIterator(): LastIdQuery
    {
        $query = $this->connection->createQueryBuilder();
        $query
            ->select(['product.auto_increment', 'product.id'])
            ->from('product')
            ->andWhere('product.auto_increment > :lastId')
            ->addOrderBy('product.auto_increment')
            ->setMaxResults(50)
            ->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }
}
