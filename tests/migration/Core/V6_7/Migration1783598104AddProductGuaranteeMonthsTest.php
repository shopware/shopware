<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1783598104AddProductGuaranteeMonths;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(Migration1783598104AddProductGuaranteeMonths::class)]
class Migration1783598104AddProductGuaranteeMonthsTest extends TestCase
{
    private readonly Connection $connection;

    private readonly Migration1783598104AddProductGuaranteeMonths $migration;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1783598104AddProductGuaranteeMonths();

        try {
            $this->connection->executeStatement('ALTER TABLE `product` DROP COLUMN `guarantee_months`;');
        } catch (\Throwable) {
        }
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1783598104, $this->migration->getCreationTimestamp());
    }

    public function testAddColumn(): void
    {
        static::assertFalse(TableHelper::columnExists($this->connection, 'product', 'guarantee_months'));

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'product', 'guarantee_months');
        static::assertSame('integer', $column->type);
    }
}
