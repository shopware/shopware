<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Handler;

use Shopware\Core\Framework\App\AppException;
use Shopware\Core\Framework\App\ShopIdChangeResolver\ReinstallAppsStrategy;
use Shopware\Core\Framework\App\ShopIdChangeResolver\Resolver;
use Shopware\Core\Framework\App\ShopIdChangeResolver\UninstallAppsStrategy;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;

/**
 * @internal
 */
#[Package('framework')]
readonly class StagingAppHandler
{
    public function __construct(
        private Resolver $shopIdChangeResolver,
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        $strategy = $this->strategyName($event);

        try {
            $this->shopIdChangeResolver->resolve($strategy, $event->context);
        } catch (AppException $e) {
            $event->io->error($e->getMessage());
            $event->canceled = true;

            return;
        }

        $event->io->info(\sprintf('Staging: applied the shop ID change strategy "%s".', $strategy));
        $event->io->note('Make sure this installation has its own SHOPWARE_CACHE_ID or its own cache server, otherwise it can share the cache, and the shop ID, with the original shop.');
    }

    private function strategyName(SetupStagingEvent $event): string
    {
        if ($event->reinstallApps) {
            return ReinstallAppsStrategy::STRATEGY_NAME;
        }

        return UninstallAppsStrategy::STRATEGY_NAME;
    }
}
