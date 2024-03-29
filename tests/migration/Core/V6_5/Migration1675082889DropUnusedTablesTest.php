<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1675082889DropUnusedTables;

/**
 * @internal
 */
#[CoversClass(Migration1675082889DropUnusedTables::class)]
class Migration1675082889DropUnusedTablesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS `message_queue_stats` (id BINARY(16) NOT NULL, PRIMARY KEY(id))');
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS `mail_template_sales_channel` (id BINARY(16) NOT NULL, PRIMARY KEY(id))');
            $this->connection->executeStatement('CREATE TABLE IF NOT EXISTS `sales_channel_rule` (id BINARY(16) NOT NULL, PRIMARY KEY(id))');
        } catch (\Throwable $e) {
        }
    }

    public function testRunMultipleTimes(): void
    {
        $m = new Migration1675082889DropUnusedTables();

        $m->update($this->connection);
        $m->update($this->connection);

        $triggers = array_flip(array_column($this->connection->fetchAllAssociative('SHOW TRIGGERS'), 'Trigger'));
        static::assertArrayNotHasKey('customer_address_vat_id_insert', $triggers);
        static::assertArrayNotHasKey('customer_address_vat_id_update', $triggers);
        static::assertArrayNotHasKey('order_cash_rounding_insert', $triggers);

        static::assertFalse(EntityDefinitionQueryHelper::tableExists($this->connection, 'message_queue_stats'));
        static::assertFalse(EntityDefinitionQueryHelper::tableExists($this->connection, 'mail_template_sales_channel'));
        static::assertFalse(EntityDefinitionQueryHelper::tableExists($this->connection, 'sales_channel_rule'));
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'customer_address', 'vat_id'));
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'customer', 'newsletter'));
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'whitelist_ids'));
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'product', 'blacklist_ids'));
    }
}
