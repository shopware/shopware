<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired by the shop-id-change Resolver after a strategy has completed.
 * Listeners can use $strategyName to gate behavior (e.g. theme cleanup on uninstall-apps) and $affectedApps for snapshot id/name pairs
 * of the apps the strategy touched. Snapshot data is required because, for the uninstall path, the underlying app rows have already been deleted.
 *
 * @internal only for use by the app-system
 */
#[Package('framework')]
final class ShopIdResolvedEvent extends Event
{
    /**
     * @param list<array{id: string, name: string}> $affectedApps
     */
    public function __construct(
        public readonly string $strategyName,
        public readonly array $affectedApps,
        public readonly Context $context,
    ) {
    }
}
