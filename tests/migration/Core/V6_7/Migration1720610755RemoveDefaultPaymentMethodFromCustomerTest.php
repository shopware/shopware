<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\BasicTestDataBehaviour;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Migration\V6_7\Migration1720610755RemoveDefaultPaymentMethodFromCustomer;

/**
 * @internal
 */
#[CoversClass(Migration1720610755RemoveDefaultPaymentMethodFromCustomer::class)]
class Migration1720610755RemoveDefaultPaymentMethodFromCustomerTest extends TestCase
{
    use BasicTestDataBehaviour;
    use KernelTestBehaviour;

    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = static::getContainer()->get(Connection::class);
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1720610755, (new Migration1720610755RemoveDefaultPaymentMethodFromCustomer())->getCreationTimestamp());
    }

    public function testUpdateMakesColumnNullable(): void
    {
        if (!TableHelper::columnExists($this->connection, 'customer', 'default_payment_method_id')) {
            $this->addColumn();
        }

        $migration = new Migration1720610755RemoveDefaultPaymentMethodFromCustomer();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = TableHelper::getColumnOfTable($this->connection, 'customer', 'default_payment_method_id');
        static::assertFalse($column->isNotNull);
    }

    public function testUpdateDestructiveRemovesColumn(): void
    {
        $exists = TableHelper::columnExists($this->connection, 'customer', 'default_payment_method_id');

        if (!$exists) {
            $this->addColumn();
        }

        $migration = new Migration1720610755RemoveDefaultPaymentMethodFromCustomer();
        $migration->updateDestructive($this->connection);
        $migration->updateDestructive($this->connection);

        static::assertFalse(TableHelper::columnExists($this->connection, 'customer', 'default_payment_method_id'));

        if ($exists) {
            $this->addColumn();
        }
    }

    private function addColumn(): void
    {
        $this->connection
            ->executeStatement(
                'ALTER TABLE `customer`
                    ADD COLUMN `default_payment_method_id` BINARY(16) NOT NULL DEFAULT :defaultPaymentMethodId,
                    ADD CONSTRAINT `fk.customer.default_payment_method_id` FOREIGN KEY (`default_payment_method_id`) REFERENCES `payment_method` (`id`) ON DELETE RESTRICT ON UPDATE CASCADE',
                ['defaultPaymentMethodId' => Uuid::fromHexToBytes($this->getValidPaymentMethodId())]
            );
    }
}
