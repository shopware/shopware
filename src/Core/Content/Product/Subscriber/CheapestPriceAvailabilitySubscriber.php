<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Subscriber;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexer;
use Shopware\Core\Content\Product\DataAbstractionLayer\ProductIndexingMessage;
use Shopware\Core\Content\Product\Events\ProductNoLongerAvailableEvent;
use Shopware\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopware\Core\Content\Product\Stock\StockStorage;
use Shopware\Core\Framework\DataAbstractionLayer\Indexing\EntityIndexerRegistry;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Keeps the persisted cheapest price in sync with runtime availability changes.
 *
 * The cheapest price container persists `is_closeout` / `available` per variant so
 * unavailable closeout variants can be excluded from the cheapest price aggregation
 * (see issue #16239). Stock changes caused by orders bypass the product indexer
 * ({@see StockStorage::alter()} updates the `available` flag with plain SQL), so the
 * persisted flags would stay stale until the next product write.
 *
 * {@see ProductNoLongerAvailableEvent} alone is not enough to act on: it is also
 * dispatched while the product indexer recomputes the available flag during regular
 * product writes, where the cheapest price is updated anyway. Therefore the flipped
 * ids are only collected, and a cheapest-price-only indexing run is scheduled once
 * {@see ProductStockAlteredEvent} confirms the change originated from a runtime
 * stock alteration (orders being placed, cancelled or refunded).
 *
 * @internal
 */
#[Package('inventory')]
class CheapestPriceAvailabilitySubscriber implements EventSubscriberInterface, ResetInterface
{
    /**
     * @var list<string>
     */
    private array $flippedProductIds = [];

    /**
     * @internal
     */
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityIndexerRegistry $indexerRegistry,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductNoLongerAvailableEvent::class => 'onAvailabilityFlipped',
            ProductStockAlteredEvent::class => 'scheduleCheapestPriceUpdate',
        ];
    }

    /**
     * Collects the products whose `available` flag actually changed - in either
     * direction, the event is dispatched for every flag change.
     */
    public function onAvailabilityFlipped(ProductNoLongerAvailableEvent $event): void
    {
        $this->flippedProductIds = \array_values(\array_unique([...$this->flippedProductIds, ...$event->getIds()]));
    }

    public function scheduleCheapestPriceUpdate(ProductStockAlteredEvent $event): void
    {
        $flippedIds = \array_values(\array_intersect($this->flippedProductIds, $event->getIds()));
        $this->flippedProductIds = [];

        if ($flippedIds === []) {
            return;
        }

        $indexer = $this->indexerRegistry->getIndexer('product.indexer');

        if ($indexer === null) {
            return;
        }

        $parentIds = $this->mapToParentIds($flippedIds, $event->getContext()->getVersionId());

        if ($parentIds === []) {
            return;
        }

        $message = new ProductIndexingMessage($parentIds, null, $event->getContext());
        $message->setIndexer($indexer->getName());
        // Only the availability flags changed, the price data itself is unchanged,
        // so every other updater of the product indexer can be skipped
        $message->setSkip(\array_diff($indexer->getOptions(), [ProductIndexer::CHEAPEST_PRICE_UPDATER]));

        $this->messageBus->dispatch($message);
    }

    public function reset(): void
    {
        $this->flippedProductIds = [];
    }

    /**
     * The cheapest price updater operates on parent ids, the event carries the
     * ids of the products whose availability flipped (mostly variants).
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function mapToParentIds(array $ids, string $versionId): array
    {
        $ids = \array_unique(\array_filter($ids));

        if ($ids === []) {
            return [];
        }

        /** @var list<string> $parentIds */
        $parentIds = $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(`parent_id`, `id`)))
             FROM product
             WHERE `id` IN (:ids)
             AND `version_id` = :version',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'version' => Uuid::fromHexToBytes($versionId),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );

        return $parentIds;
    }
}
