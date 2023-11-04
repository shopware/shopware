<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1647511158AddRefundUrlToAppPaymentMethod;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1647511158AddRefundUrlToAppPaymentMethod
 */
class Migration1647511158AddRefundUrlToAppPaymentMethodTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationCanExecuteMultipleTimes(): void
    {
        $migration = new Migration1647511158AddRefundUrlToAppPaymentMethod();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $appPaymentMethodColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM `app_payment_method`'), 'Field');

        static::assertContains('refund_url', $appPaymentMethodColumns);
    }
}
