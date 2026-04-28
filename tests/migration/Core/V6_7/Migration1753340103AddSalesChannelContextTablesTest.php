<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_7\Migration1753340103AddSalesChannelContextTables;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(Migration1753340103AddSalesChannelContextTables::class)]
class Migration1753340103AddSalesChannelContextTablesTest extends TestCase
{
    use KernelTestBehaviour;

    public function testMigration(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $this->revertMigration($connection);

        $sm = $connection->createSchemaManager();
        static::assertFalse($sm->tablesExist(['sales_channel_context', 'sales_channel_context_token']));

        $migration = new Migration1753340103AddSalesChannelContextTables();

        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($sm->tablesExist(['sales_channel_context', 'sales_channel_context_token']));
    }

    public function testDestructiveMigration(): void
    {
        $connection = $this->getContainer()->get(Connection::class);

        $this->revertDestructiveMigration($connection);

        $sm = $connection->createSchemaManager();
        static::assertTrue($sm->tablesExist(['sales_channel_api_context']));

        $migration = new Migration1753340103AddSalesChannelContextTables();

        $migration->updateDestructive($connection);
        $migration->updateDestructive($connection);

        static::assertFalse($sm->tablesExist(['sales_channel_api_context']));
    }

    private function revertMigration(Connection $connection): void
    {
        $connection->executeStatement('DROP TABLE IF EXISTS `sales_channel_context_token`;');
        $connection->executeStatement('DROP TABLE IF EXISTS `sales_channel_context`;');
    }

    private function revertDestructiveMigration(Connection $connection): void
    {
        $connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_api_context` (
            `id` BINARY(16) NOT NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;');
    }
}
