<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1634735841AddedNewsletterSalesChannelIds;
use Shopware\Tests\Migration\MigrationTestTrait;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1634735841AddedNewsletterSalesChannelIds
 */
class Migration1634735841AddedNewsletterSalesChannelIdsTest extends TestCase
{
    use MigrationTestTrait;

    private Connection $connection;

    private Migration1634735841AddedNewsletterSalesChannelIds $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        $this->migration = new Migration1634735841AddedNewsletterSalesChannelIds();
    }

    public function testMigration(): void
    {
        $this->connection->rollBack();
        $this->prepare();
        $this->migration->update($this->connection);
        $this->connection->beginTransaction();

        // check if migration ran successfully
        $resultColumnExists = $this->hasColumn('customer', 'newsletter_sales_channel_ids');

        static::assertTrue($resultColumnExists);
    }

    private function prepare(): void
    {
        $resultColumnExists = $this->hasColumn('customer', 'newsletter_sales_channel_ids');

        if ($resultColumnExists) {
            $this->connection->executeStatement('ALTER TABLE `customer` DROP COLUMN `newsletter_sales_channel_ids`');
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
