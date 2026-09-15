<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\PHPStan\Rules\data\NonStandardFkGuardRule;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

/**
 * @internal
 */
class Migration1785000002GuardedDdl extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1785000002;
    }

    public function update(Connection $connection): void
    {
        $this->executeDdlStatement($connection, 'ALTER TABLE `product` ADD COLUMN `foo` VARCHAR(32) NULL');

        $this->executeDdlStatement($connection, 'CREATE INDEX `idx.product.foo` ON `product` (`foo`)');

        // The MigrationStep DDL helpers run through the guard themselves
        $this->addColumn($connection, 'product', 'bar', 'VARCHAR(32)');
    }

    public function updateDestructive(Connection $connection): void
    {
        $this->dropColumnIfExists($connection, 'product', 'foo');
    }
}
