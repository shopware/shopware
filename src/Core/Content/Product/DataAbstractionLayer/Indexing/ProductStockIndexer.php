<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\DataAbstractionLayer\Indexing;

use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Order\OrderStates;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\Common\IteratorFactory;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenContainerEvent;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\IndexerInterface;
use Shopware\Core\Framework\Event\ProgressAdvancedEvent;
use Shopware\Core\Framework\Event\ProgressFinishedEvent;
use Shopware\Core\Framework\Event\ProgressStartedEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class ProductStockIndexer implements IndexerInterface, EventSubscriberInterface
{
    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var IteratorFactory
     */
    private $iteratorFactory;

    /**
     * @var ProductDefinition
     */
    private $definition;

    /**
     * @var EventDispatcherInterface
     */
    private $eventDispatcher;

    /**
     * @var TagAwareAdapterInterface
     */
    private $cache;

    /**
     * @var EntityCacheKeyGenerator
     */
    private $cacheKeyGenerator;

    public function __construct(
        Connection $connection,
        IteratorFactory $iteratorFactory,
        ProductDefinition $definition,
        EventDispatcherInterface $eventDispatcher,
        TagAwareAdapterInterface $cache,
        EntityCacheKeyGenerator $cacheKeyGenerator
    ) {
        $this->connection = $connection;
        $this->iteratorFactory = $iteratorFactory;
        $this->definition = $definition;
        $this->eventDispatcher = $eventDispatcher;
        $this->cache = $cache;
        $this->cacheKeyGenerator = $cacheKeyGenerator;
    }

    /**
     * Returns a list of custom business events to listen where the product maybe changed
     */
    public static function getSubscribedEvents()
    {
        return [
            CheckoutOrderPlacedEvent::class => 'orderPlaced',
            StateMachineTransitionEvent::class => 'stateChanged',
        ];
    }

    public function index(\DateTimeInterface $timestamp): void
    {
        $context = Context::createDefaultContext();
        $iterator = $this->iteratorFactory->createIterator($this->definition);

        $this->eventDispatcher->dispatch(
            new ProgressStartedEvent('Start indexing product availability', $iterator->fetchCount()),
            ProgressStartedEvent::NAME
        );

        while ($ids = $iterator->fetch()) {
            $this->updateAvailableStock($ids, $context);

            $this->updateAvailableFlag($ids, $context);

            $this->eventDispatcher->dispatch(
                new ProgressAdvancedEvent(\count($ids)),
                ProgressAdvancedEvent::NAME
            );
        }

        $this->eventDispatcher->dispatch(
            new ProgressFinishedEvent('Finished indexing product availability'),
            ProgressFinishedEvent::NAME
        );
    }

    public function partial(?array $lastId, \DateTimeInterface $timestamp): ?array
    {
        $context = Context::createDefaultContext();
        $iterator = $this->iteratorFactory->createIterator($this->definition, $lastId);

        $ids = $iterator->fetch();

        if (empty($ids)) {
            return null;
        }

        $this->updateAvailableStock($ids, $context);
        $this->updateAvailableFlag($ids, $context);

        return $iterator->getOffset();
    }

    public function refresh(EntityWrittenContainerEvent $event): void
    {
        $products = $event->getEventByDefinition(ProductDefinition::class);

        $ids = [];
        if ($products) {
            $ids = $products->getIds();
        }

        $this->updateAvailableStock($ids, $event->getContext());

        $this->updateAvailableFlag($ids, $event->getContext());
    }

    public function stateChanged(StateMachineTransitionEvent $event): void
    {
        if ($event->getEntityName() !== 'order') {
            return;
        }

        $multiplier = null;
        if ($event->getToPlace()->getTechnicalName() === OrderStates::STATE_COMPLETED) {
            $multiplier = -1;
        }

        if ($event->getFromPlace()->getTechnicalName() === OrderStates::STATE_COMPLETED) {
            $multiplier = +1;
        }

        if ($multiplier === null) {
            return;
        }

        $products = $this->getProductsOfOrder($event->getEntityId());

        $ids = array_column($products, 'referenced_id');

        $this->updateStock($products, $multiplier);

        $this->updateAvailableStock($ids, $event->getContext());

        $this->updateAvailableFlag($ids, $event->getContext());

        $this->clearCache($ids);
    }

    public function orderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $ids = [];
        foreach ($event->getOrder()->getLineItems() as $lineItem) {
            if ($lineItem->getType() !== LineItem::PRODUCT_LINE_ITEM_TYPE) {
                continue;
            }
            $ids[] = $lineItem->getReferencedId();
        }

        $this->updateAvailableStock($ids, $event->getContext());

        $this->updateAvailableFlag($ids, $event->getContext());

        $this->clearCache($ids);
    }

    private function updateAvailableStock(array $ids, Context $context): void
    {
        $ids = array_filter(array_keys(array_flip($ids)));

        if (empty($ids)) {
            return;
        }

        $bytes = Uuid::fromHexToBytesList($ids);

        $sql = '
UPDATE product SET available_stock = stock - (
    SELECT IFNULL(SUM(order_line_item.quantity), 0)

    FROM order_line_item
        INNER JOIN `order`
            ON `order`.id = order_line_item.order_id
            AND `order`.version_id = order_line_item.order_version_id
        INNER JOIN state_machine_state
            ON state_machine_state.id = `order`.state_id
            AND state_machine_state.technical_name NOT IN (:states)

    WHERE LOWER(order_line_item.referenced_id) = LOWER(HEX(product.id))
    AND order_line_item.type = :type
    AND order_line_item.version_id = :version
)
WHERE product.id IN (:ids);
        ';

        $this->connection->executeUpdate(
            $sql,
            [
                'type' => LineItem::PRODUCT_LINE_ITEM_TYPE,
                'version' => Uuid::fromHexToBytes($context->getVersionId()),
                'states' => [OrderStates::STATE_COMPLETED, OrderStates::STATE_CANCELLED],
                'ids' => $bytes,
            ],
            [
                'ids' => Connection::PARAM_STR_ARRAY,
                'states' => Connection::PARAM_STR_ARRAY,
            ]
        );
    }

    private function updateStock(array $products, int $multiplier): void
    {
        $query = $this->connection->prepare('UPDATE product SET stock = stock + :quantity WHERE id = :id AND version_id = :version');

        foreach ($products as $product) {
            $query->execute([
                'quantity' => (int) $product['quantity'] * $multiplier,
                'id' => Uuid::fromHexToBytes($product['referenced_id']),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ]);
        }
    }

    private function updateAvailableFlag(array $ids, Context $context): void
    {
        $ids = array_filter(array_keys(array_flip($ids)));

        if (empty($ids)) {
            return;
        }

        $bytes = Uuid::fromHexToBytesList($ids);

        $sql = '
            UPDATE product
            LEFT JOIN product parent 
                ON parent.id = product.parent_id AND parent.version_id = product.version_id
            
            SET product.available = IFNULL((
                IFNULL(product.is_closeout, parent.is_closeout) * product.available_stock 
                >=
                IFNULL(product.is_closeout, parent.is_closeout) * IFNULL(product.min_purchase, parent.min_purchase)
            ), 0)
            WHERE product.id IN (:ids)
            AND product.version_id = :version
        ';

        $this->connection->executeUpdate(
            $sql,
            ['ids' => $bytes, 'version' => Uuid::fromHexToBytes($context->getVersionId())],
            ['ids' => Connection::PARAM_STR_ARRAY]
        );
    }

    private function getProductsOfOrder(string $orderId): array
    {
        $query = $this->connection->createQueryBuilder();
        $query->select(['referenced_id', 'quantity']);
        $query->from('order_line_item');
        $query->andWhere('type = :type');
        $query->andWhere('order_id = :id');
        $query->setParameter('id', Uuid::fromHexToBytes($orderId));
        $query->setParameter('type', LineItem::PRODUCT_LINE_ITEM_TYPE);

        return $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function clearCache(array $ids): void
    {
        $tags = [];
        foreach ($ids as $id) {
            $tags[] = $this->cacheKeyGenerator->getEntityTag($id, $this->definition);
        }

        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'id');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'available');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'availableStock');
        $tags[] = $this->cacheKeyGenerator->getFieldTag($this->definition, 'stock');

        $this->cache->invalidateTags($tags);
    }
}
