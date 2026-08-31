<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NonStandardFkGuardRule;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785000001UnguardedDdl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785000001;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('ALTER TABLE `product` ADD COLUMN `foo` VARCHAR(32) NULL');

        $connection->executeStatement('CREATE INDEX `idx.product.foo` ON `product` (`foo`)');

        $connection->executeStatement('DROP INDEX `idx.product.foo` ON `product`');

        // Not in TABLES_WITH_KNOWN_DRIFT, so not reported
        $connection->executeStatement('ALTER TABLE `order_line_item` ADD COLUMN `foo` VARCHAR(32) NULL');
        $connection->executeStatement('ALTER TABLE `media` ADD COLUMN `foo` VARCHAR(32) NULL');

        // Not DDL, so not reported
        $connection->executeStatement('UPDATE `product` SET `foo` = NULL');
    }
}
