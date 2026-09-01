<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Migration\Reversible\_fixtures\SwagIntegration\Migration;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Migration\Reversible\DownMigrationContext;
use Shopware\Core\Framework\Migration\Reversible\Migration;
use Shopware\Core\Framework\Migration\Reversible\UpMigrationContext;

/**
 * @internal
 */
#[Package('framework')]
class Migration1900000000CreateTable extends Migration
{
    final public const TABLE = 'swag_reversible_integration';

    public function getCreationTimestamp(): int
    {
        return 1900000000;
    }

    public function up(UpMigrationContext $context): void
    {
        $context->connection->executeStatement(\sprintf(
            'CREATE TABLE IF NOT EXISTS `%s` (`id` BINARY(16) NOT NULL, PRIMARY KEY (`id`))
             ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
            self::TABLE
        ));
    }

    public function down(DownMigrationContext $context): void
    {
        $context->connection->executeStatement(\sprintf('DROP TABLE IF EXISTS `%s`', self::TABLE));
    }
}
