<?php declare(strict_types=1);

namespace SwagTestReversibleMigration\Migration;

use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * @internal
 */
class Migration1900000001CreateTable extends Migration
{
    final public const TABLE = 'swag_test_reversible_migration';

    final public const TIMESTAMP = 1900000001;

    public function getCreationTimestamp(): int
    {
        return self::TIMESTAMP;
    }

    public function up(UpMigrationContext $context): void
    {
        $context->connection->executeStatement(\sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (
                `id` BINARY(16) NOT NULL,
                `was_installation` TINYINT(1) NOT NULL,
                PRIMARY KEY (`id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            self::TABLE
        ));

        // record the flag so the test can assert what the lifecycle passed in
        $context->connection->insert(self::TABLE, [
            'id' => random_bytes(16),
            'was_installation' => $context->isInstallation ? 1 : 0,
        ]);
    }

    public function down(DownMigrationContext $context): void
    {
        $context->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', self::TABLE));
    }
}
