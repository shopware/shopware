<?php declare(strict_types=1);

namespace Shopware\Core\Migration\V6_4;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1636964297AddDefaultTaxRate extends MigrationStep
{
    public const CONFIG_KEY = 'core.tax.defaultTaxRate';

    public function getCreationTimestamp(): int
    {
        return 1636964297;
    }

    public function update(Connection $connection): void
    {
        if ($connection->fetchOne('SELECT 1 FROM `system_config` WHERE `configuration_key` = ? LIMIT 1', [self::CONFIG_KEY])) {
            return;
        }

        $id = $connection->fetchOne('SELECT `id` FROM `tax` WHERE `name` = ? LIMIT 1', ['Standard rate']);
        if ($id) {
            $connection->insert('system_config', [
                'id' => Uuid::randomBytes(),
                'configuration_key' => self::CONFIG_KEY,
                'configuration_value' => json_encode(['_value' => Uuid::fromBytesToHex($id)]),
                'created_at' => (new \DateTime())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
        // implement update destructive
    }
}
