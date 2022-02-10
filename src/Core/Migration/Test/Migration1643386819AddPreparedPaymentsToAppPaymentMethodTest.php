<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1643386819AddPreparedPaymentsToAppPaymentMethod;

class Migration1643386819AddPreparedPaymentsToAppPaymentMethodTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
    }

    public function testMigrationCanExecuteMultipleTimes(): void
    {
        // Rollback Migration
        $this->connection->executeStatement('ALTER TABLE `app_payment_method` DROP COLUMN `validate_url`, DROP COLUMN `capture_url`');

        $migration = new Migration1643386819AddPreparedPaymentsToAppPaymentMethod();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $appPaymentMethodColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM `app_payment_method`'), 'Field');
        static::assertContains('validate_url', $appPaymentMethodColumns);
        static::assertContains('capture_url', $appPaymentMethodColumns);
    }
}
