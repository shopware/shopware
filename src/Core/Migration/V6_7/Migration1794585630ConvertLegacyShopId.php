<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Composer\InstalledVersions;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Util\Hasher;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1794585630ConvertLegacyShopId extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1794585630;
    }

    public function update(Connection $connection): void
    {
        $configs = $connection->fetchAllKeyValue(
            'SELECT `configuration_key`, `configuration_value` FROM `system_config` WHERE `configuration_key` IN (\'core.app.shopId\', \'core.app.shopIdV2\') AND `sales_channel_id` IS NULL'
        );

        $legacyShopId = json_decode((string) ($configs['core.app.shopId'] ?? ''), true)['_value'] ?? null;
        if (isset($configs['core.app.shopIdV2']) || !\is_string($legacyShopId['value'] ?? null) || !\is_string($legacyShopId['app_url'] ?? null)) {
            return;
        }

        $shopId = [
            'id' => $legacyShopId['value'],
            'fingerprints' => [
                'app_url' => $legacyShopId['app_url'],
                'installation_path' => ($_ENV['PROJECT_ROOT'] ?? $_SERVER['PROJECT_ROOT'] ?? null) ?: realpath(InstalledVersions::getRootPackage()['install_path']),
                'sales_channel_domain_urls' => Hasher::hash(implode('', $connection->fetchFirstColumn('SELECT url FROM sales_channel_domain')), 'md5'),
            ],
            'version' => 2,
        ];

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => 'core.app.shopIdV2',
            'configuration_value' => json_encode(['_value' => $shopId], \JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }
}
