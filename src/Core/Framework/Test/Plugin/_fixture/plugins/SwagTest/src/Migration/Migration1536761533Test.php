<?php declare(strict_types=1);

namespace SwagTest\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
class Migration1536761533Test extends MigrationStep
{
    final public const TEST_SYSTEM_CONFIG_KEY = 'swag_test_counter';

    final public const TIMESTAMP = 1536761533;

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
        $row = $result->fetchAssociative();

        $id = Uuid::randomBytes();
        $value = 0;

        if ($row) {
            $id = $row['id'];
            $value = $row['configuration_value'];
        }

        $connection->executeStatement(
            'REPLACE INTO system_config (id, configuration_key, configuration_value, created_at)
             VALUES (?, ?, ?, date(now()))',
            [$id, self::TEST_SYSTEM_CONFIG_KEY, $value + 1]
        );
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}
