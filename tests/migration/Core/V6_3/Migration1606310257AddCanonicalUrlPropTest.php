<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_3;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_3\Migration1606310257AddCanonicalUrlProp;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_3\Migration1606310257AddCanonicalUrlProp
 */
class Migration1606310257AddCanonicalUrlPropTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1606310257AddCanonicalUrlProp $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->migration = new Migration1606310257AddCanonicalUrlProp();
    }

    public function testMigration(): void
    {
        $this->connection->rollBack();
        $this->prepare();
        $this->migration->update($this->connection);
        $this->connection->beginTransaction();

        // check if migration ran successfully
        $canonicalProductColumnExists = $this->hasColumn('product', 'canonicalProduct');

        static::assertTrue($canonicalProductColumnExists);

        $canonicalProductIdColumnExists = $this->hasColumn('product', 'canonical_product_id');

        static::assertTrue($canonicalProductIdColumnExists);
    }

    private function prepare(): void
    {
        $canonicalProductColumnExists = $this->hasColumn('product', 'canonicalProduct');

        if ($canonicalProductColumnExists) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN canonicalProduct');
        }

        $canonicalProductIdColumnExists = $this->hasColumn('product', 'canonical_product_id');

        if ($canonicalProductIdColumnExists) {
            $this->connection->executeStatement('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.canonical_product_id`;');

            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN canonical_product_id');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static fn (Column $column): bool => $column->getName() === $columnName
        )) > 0;
    }
}
