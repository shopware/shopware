<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1661759290AddDateAndCurrencyIndexToOrderTable;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1661759290AddDateAndCurrencyIndexToOrderTable
 */
class Migration1661759290AddDateAndCurrencyIndexToOrderTableTest extends TestCase
{
    protected function setUp(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        if ($this->indexExists($connection)) {
            $connection->executeStatement('DROP INDEX `idx.order_date_currency_id` ON `order`');
        }
    }

    public function testHasCorrectTimestamp(): void
    {
        static::assertStringContainsString(
            (string) (new Migration1661759290AddDateAndCurrencyIndexToOrderTable())->getCreationTimestamp(),
            Migration1661759290AddDateAndCurrencyIndexToOrderTable::class
        );
    }

    public function testAddsIndexToOrderTable(): void
    {
        $migration = new Migration1661759290AddDateAndCurrencyIndexToOrderTable();
        $connection = KernelLifecycleManager::getConnection();

        static::assertFalse($this->indexExists($connection));

        $migration->update($connection);

        static::assertTrue($this->indexExists($connection));
    }

    public function testCanBeExecutedMultipleTimes(): void
    {
        $migration = new Migration1661759290AddDateAndCurrencyIndexToOrderTable();
        $connection = KernelLifecycleManager::getConnection();

        static::assertFalse($this->indexExists($connection));

        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->indexExists($connection));
    }

    private function indexExists(Connection $connection): bool
    {
        $index = $connection->executeQuery(
            'SHOW INDEXES FROM `order` WHERE key_name = :indexName',
            ['indexName' => 'idx.order_date_currency_id']
        )->fetchOne();

        return $index !== false;
    }
}
