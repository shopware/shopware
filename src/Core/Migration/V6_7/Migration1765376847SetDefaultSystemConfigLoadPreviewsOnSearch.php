<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_7;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('framework')]
class Migration1765376847SetDefaultSystemConfigLoadPreviewsOnSearch extends MigrationStep
{
    private const CONFIG_KEY = 'core.listing.findBestVariant';

    public function getCreationTimestamp(): int
    {
        return 1765376847;
    }

    public function update(Connection $connection): void
    {
        if ($this->hasConfigValue($connection)) {
            return;
        }

        $connection->insert('system_config', [
            'id' => Uuid::randomBytes(),
            'configuration_key' => self::CONFIG_KEY,
            'configuration_value' => '{"_value": false}',
            'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);
    }

    private function hasConfigValue(Connection $connection): bool
    {
        return (bool) $connection->fetchOne('SELECT 1 FROM `system_config` WHERE `configuration_key` = :key', [
            'key' => self::CONFIG_KEY,
        ]);
    }
}
