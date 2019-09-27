<?php declare(strict_types=1);

namespace SwagTest\Migration;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\FetchMode;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

class Migration1536761533Test extends MigrationStep
{
    public const TEST_SYSTEM_CONFIG_KEY = 'swag_test_counter';

    public const TIMESTAMP = 1536761533;

    public function getCreationTimestamp(): int
    {
        return self::TIMESTAMP;
    }

    public function update(Connection $connection): void
    {
        $result = $connection->executeQuery(
            'SELECT id, configuration_value 
             FROM system_config 
             WHERE sales_channel_id IS NULL 
               AND configuration_key = ?',
            [self::TEST_SYSTEM_CONFIG_KEY]
        );
        $row = $result->fetch(FetchMode::ASSOCIATIVE);

        $id = $row['id'] ?? Uuid::randomBytes();
        $value = $row['configuration_value'] ?? 0;

        $connection->executeUpdate(
            'REPLACE INTO system_config (id, configuration_key, configuration_value, created_at)
             VALUES (?, ?, ?, date(now()))',
            [$id, self::TEST_SYSTEM_CONFIG_KEY, $value + 1]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
