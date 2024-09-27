<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\InAppPurchase\Services;

use Doctrine\DBAL\Connection;
use GuzzleHttp\ClientInterface;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\InAppPurchase\InAppPurchaseCollection;

/**
 * @internal
 */
#[Package('checkout')]
class InAppPurchasesSyncService
{
    /**
     * @param EntityRepository<InAppPurchaseCollection> $iapRepository
     */
    public function __construct(
        private readonly ClientInterface $client,
        private readonly EntityRepository $iapRepository,
        private readonly Connection $connection,
        private readonly string $fetchEndpoint
    ) {
    }

    public function updateActiveInAppPurchases(Context $context): void
    {
        list($existingApps, $existingPlugins) = $this->fetchExistingAppsAndPlugins();

        $activeIaps = $this->fetchActiveInAppPurchasesFromSBP();

        $iapData = array_map(static function ($iap) use ($existingApps, $existingPlugins) {
            $identifier = strtok($iap['identifier'], '-') ?: '';

            return [
                'identifier' => $iap['identifier'],
                'expiresAt' => $iap['expiresAt'],
                'appId' => $existingApps[$identifier] ?? null,
                'pluginId' => $existingPlugins[$identifier] ?? null,
                'active' => true,
            ];
        }, $activeIaps);

        $this->iapRepository->upsert($iapData, $context);
    }

    public function disableExpiredInAppPurchases(): void
    {
        $this->connection->executeQuery('UPDATE in_app_purchase SET active = false WHERE expires_at < NOW()');
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    private function fetchExistingAppsAndPlugins(): array
    {
        $existingExtensions = $this->connection->fetchAllAssociative('
            SELECT `name`, LOWER(HEX(`id`)) AS `id`, "app" AS type
            FROM app
            WHERE `active` = 1
            UNION
            SELECT `name`, LOWER(HEX(`id`)) AS `id`, "plugin" AS type
            FROM plugin
            WHERE `active` = 1
        ');

        /** @var array<string, string> $existingApps */
        $existingApps = array_column(array_filter($existingExtensions, static fn ($ext) => $ext['type'] === 'app'), 'id', 'name');
        /** @var array<string, string> $existingPlugins */
        $existingPlugins = array_column(array_filter($existingExtensions, static fn ($ext) => $ext['type'] === 'plugin'), 'id', 'name');

        return [$existingApps, $existingPlugins];
    }

    /**
     * @return array<int, array{identifier: string, expiresAt: string}>
     */
    private function fetchActiveInAppPurchasesFromSBP(): array
    {
        $response = $this->client->request('GET', $this->fetchEndpoint);

        return json_decode($response->getBody()->getContents(), true);
    }
}
