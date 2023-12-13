<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1686817968AddRecurringAppPaymentMethodUrl;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(Migration1686817968AddRecurringAppPaymentMethodUrl::class)]
class Migration1686817968AddRecurringAppPaymentMethodUrlTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testMigrationCanExecuteMultipleTimes(): void
    {
        $migration = new Migration1686817968AddRecurringAppPaymentMethodUrl();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $appPaymentMethodColumns = \array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM `app_payment_method`'), 'Field');

        static::assertContains('recurring_url', $appPaymentMethodColumns);
    }
}
