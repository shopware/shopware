<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\Migration1606310257AddCanonicalUrlProp;

class Migration1606310257AddCanonicalUrlPropTest extends TestCase
{
    use IntegrationTestBehaviour;

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var Migration1606310257AddCanonicalUrlProp
     */
    private $migration;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);

        $this->migration = new Migration1606310257AddCanonicalUrlProp();
    }

    public function testMigration(): void
    {
        $this->prepare();

        $this->migration->update($this->connection);

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
            $this->connection->executeUpdate('ALTER TABLE `product` DROP COLUMN canonicalProduct');
        }

        $canonicalProductIdColumnExists = $this->hasColumn('product', 'canonical_product_id');

        if ($canonicalProductIdColumnExists) {
            $this->connection->executeUpdate('ALTER TABLE `product` DROP FOREIGN KEY `fk.product.canonical_product_id`;');

            $this->connection->executeUpdate('ALTER TABLE `product` DROP COLUMN canonical_product_id');
        }
    }

    private function hasColumn(string $table, string $columnName): bool
    {
        return \count(array_filter(
            $this->connection->getSchemaManager()->listTableColumns($table),
            static function (Column $column) use ($columnName): bool {
                return $column->getName() === $columnName;
            }
        )) > 0;
    }
}
