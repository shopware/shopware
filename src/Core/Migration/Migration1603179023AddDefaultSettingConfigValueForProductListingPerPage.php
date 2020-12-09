<?php declare(strict_types=1);

namespace Shopware\Core\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1603179023AddDefaultSettingConfigValueForProductListingPerPage extends MigrationStep
{
    public const CONFIG_KEY = 'core.listing.productsPerPage';

    public function getCreationTimestamp(): int
    {
        return 1603179023;
    }

    public function update(Connection $connection): void
    {
        if ($this->configPresent($connection)) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => json_encode(['_value' => 24]),
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }

    private function configPresent(Connection $connection): bool
    {
        return $connection->fetchColumn(
            'SELECT `id` FROM `system_config` WHERE `configuration_key` = :config_key LIMIT 1;',
            ['config_key' => self::CONFIG_KEY]
        ) !== false;
    }
}
