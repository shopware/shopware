<?php declare(strict_types=1);

namespace Shopware\Core\Framework\App\Event;

use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Fired by the shop-id-change Resolver after a strategy has run.
 * Listeners can gate on $strategyName (e.g. theme cleanup for the uninstall-apps strategy) and read $affectedApps for the
 * id/name pairs of all apps that were installed when resolution started. The Resolver snapshots them BEFORE running the
 * strategy because the uninstall path deletes the app rows, yet downstream listeners still need their technical names.
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
