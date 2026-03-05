<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Stock;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Content\Product\Events\ProductStockAlteredEvent;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('inventory')]
final readonly class ProductStockChangedCacheInvalidationListener implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private Connection $connection,
        private CacheInvalidator $cacheInvalidator,
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ProductStockAlteredEvent::class => 'onStockAltered',
        ];
    }

    public function onStockAltered(ProductStockAlteredEvent $event): void
    {
        $ids = $event->getIds();

        if ($ids === []) {
            return;
        }

        $parentIds = $this->resolveParentIdsToInvalidate($ids);

        if ($parentIds === []) {
            return;
        }

        $this->cacheInvalidator->invalidate(
            array_map(ProductDetailRoute::buildName(...), $parentIds),
            false
        );
    }

    /**
     * Returns distinct parent IDs for closeout products that require PDP cache invalidation.
     *
     * Non-closeout products never affect calculatedMaxPurchase in core, so they are always skipped.
     * All closeout products are invalidated unconditionally — no stock-vs-maxPurchase comparison is
     * attempted here because:
     *   - StockStorage is decoratable, so the DB stock value may not reflect the effective stock;
     *   - the maxPurchase fallback (core.cart.maxQuantity) is sales-channel-aware, but this listener
     *     only receives a plain Context (no SalesChannelContext), making cross-channel comparison
     *     unreliable;
     *   - replicating ProductMaxPurchaseCalculator logic would couple invalidation to business rules
     *     that may be decorated independently.
     *
     * Extensions that make stock affect non-closeout products must handle their own invalidation.
     *
     * @param list<string> $ids
     *
     * @return list<string>
     */
    private function resolveParentIdsToInvalidate(array $ids): array
    {
        return $this->connection->fetchFirstColumn(
            'SELECT DISTINCT LOWER(HEX(COALESCE(p.parent_id, p.id)))
            FROM product p
            LEFT JOIN product parent ON parent.id = p.parent_id AND parent.version_id = p.version_id
            WHERE p.id IN (:ids)
            AND p.version_id = :version
            AND COALESCE(p.is_closeout, parent.is_closeout, 0) = 1',
            [
                'ids' => Uuid::fromHexToBytesList($ids),
                'version' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
            ],
            ['ids' => ArrayParameterType::BINARY]
        );
    }
}
