<?php declare(strict_types=1);

namespace Shopware\Core\Maintenance\Staging\Handler;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Maintenance\Staging\Event\SetupStagingEvent;

/**
 * @internal
 */
#[Package('framework')]
readonly class StagingAppHandler
{
    public function __construct(
        private Connection $connection,
        private ShopIdProvider $shopIdProvider
    ) {
    }

    public function __invoke(SetupStagingEvent $event): void
    {
        $this->deleteAppsWithAppServer($event);

        $this->shopIdProvider->deleteShopId();
    }

    private function deleteAppsWithAppServer(SetupStagingEvent $event): void
    {
        $apps = $this->connection->fetchAllAssociative('SELECT id, integration_id, name FROM app WHERE app_secret IS NOT NULL');

        foreach ($apps as $app) {
            $this->connection->delete('app', ['id' => $app['id']]);
            $this->connection->delete('integration', ['id' => $app['integration_id']]);

            $event->io->info(\sprintf('Uninstalled app %s, install app again to establish a correct connection ', $app['name']));
        }
    }
}
