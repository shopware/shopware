<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1655730949AddIsRunningColumnToProductExport;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1655730949AddIsRunningColumnToProductExport
 */
class Migration1655730949AddIsRunningColumnToProductExportTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1655730949AddIsRunningColumnToProductExport $migration;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->migration = new Migration1655730949AddIsRunningColumnToProductExport();
        $this->prepare();
    }

    public function testMigration(): void
    {
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'product_export', 'is_running'));

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'product_export', 'is_running'));
    }

    private function prepare(): void
    {
        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'product_export', 'is_running')) {
            $this->connection->executeStatement('ALTER TABLE `product_export` DROP COLUMN `is_running`');
        }
    }
}
