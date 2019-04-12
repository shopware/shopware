<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryDefinition;
use Shopware\Core\Checkout\Order\Aggregate\OrderDeliveryPosition\OrderDeliveryPositionDefinition;
use Shopware\Core\Content\Product\Service\ProductAvailableStockCalculationService;
use Shopware\Core\Content\Product\Util\EventIdExtractor;
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

    /**
     * @var EventIdExtractor
     */
    private $eventIdExtractor;

    public function __construct(
        Connection $connection,
        ProductAvailableStockCalculationService $productAvailableStockCalculationService,
        EventDispatcherInterface $eventDispatcher,
        EventIdExtractor $eventIdExtractor
    ) {
        $this->connection = $connection;
        $this->productAvailableStockCalculationService = $productAvailableStockCalculationService;
        $this->eventDispatcher = $eventDispatcher;
        $this->eventIdExtractor = $eventIdExtractor;
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();

        $iterator = $this->createIterator();

        $this->eventDispatcher->dispatch(
            ProgressStartedEvent::NAME,
            new ProgressStartedEvent('Start indexing available stocks of products', $iterator->fetchCount())
        );

        while ($ids = $iterator->fetch()) {
            $ids = array_map(function ($id) {
                return Uuid::fromBytesToHex($id);
            }, $ids);

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
            $this->getAffectedProductIds($event),
            $event->getContext()
        );
    }

    private function getAffectedProductIds(EntityWrittenContainerEvent $event): array
    {
        $productIds = $this->eventIdExtractor->getProductIds($event);

        // Collect product IDs affected by any changed order deliveries
        $deliveryEvent = $event->getEventByDefinition(OrderDeliveryDefinition::class);
        if ($deliveryEvent && count($deliveryEvent->getIds()) > 0) {
            $sql = <<<SQL
SELECT TRIM(BOTH '"' FROM JSON_EXTRACT(`order_line_item`.`payload`, '$.id')) AS `product_id`
FROM `order_delivery`
LEFT JOIN `order_delivery_position`
    ON `order_delivery_position`.`order_delivery_id` = `order_delivery`.`id`
LEFT JOIN `order_line_item`
    ON `order_line_item`.`id` = `order_delivery_position`.`order_line_item_id`
WHERE
    `order_delivery`.`id` IN (:ids)
    AND `order_line_item`.`type` = 'product'
SQL;

            $deliveryProductIds = $this->connection->fetchAll(
                $sql,
                ['ids' => array_map([Uuid::class, 'fromHexToBytes'], $deliveryEvent->getIds())],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
            $productIds = array_merge($productIds, array_column($deliveryProductIds, 'product_id'));
        }

        // Collect product IDs affected by any changed order delivery products
        $deliveryPositionEvent = $event->getEventByDefinition(OrderDeliveryPositionDefinition::class);
        if ($deliveryPositionEvent && count($deliveryPositionEvent->getIds()) > 0) {
            $sql = <<<SQL
SELECT TRIM(BOTH '"' FROM JSON_EXTRACT(`order_line_item`.`payload`, '$.id')) AS `product_id`
FROM `order_delivery_position`
LEFT JOIN `order_line_item`
    ON `order_line_item`.`id` = `order_delivery_position`.`order_line_item_id`
WHERE
    `order_delivery_position`.`id` IN (:ids)
    AND `order_line_item`.`type` = 'product'
SQL;

            $deliveryPositionProductIds = $this->connection->fetchAll(
                $sql,
                ['ids' => array_map([Uuid::class, 'fromHexToBytes'], $deliveryPositionEvent->getIds())],
                ['ids' => Connection::PARAM_STR_ARRAY]
            );
            $productIds = array_merge($productIds, array_column($deliveryPositionProductIds, 'product_id'));
        }

        return $productIds;
    }

    private function createIterator(): LastIdQuery
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['product.auto_increment', 'product.id']);
        $query->from('product');
        $query->andWhere('product.auto_increment > :lastId');
        $query->addOrderBy('product.auto_increment');

        $query->setMaxResults(50);

        $query->setParameter('lastId', 0);

        return new LastIdQuery($query);
    }
}
