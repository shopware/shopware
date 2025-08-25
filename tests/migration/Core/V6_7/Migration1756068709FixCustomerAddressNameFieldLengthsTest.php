<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1756068709FixCustomerAddressNameFieldLengths;

/**
 * @internal
 */
#[CoversClass(Migration1756068709FixCustomerAddressNameFieldLengths::class)]
class Migration1756068709FixCustomerAddressNameFieldLengthsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    /**
     * @throws Exception
     */
    public function testMigration(): void
    {
        $migration = new Migration1756068709FixCustomerAddressNameFieldLengths();
        static::assertSame(1756068709, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        $customerAddressColumns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `customer_address`');

        static::assertStringContainsString('varchar(255)', $customerAddressColumns['first_name']['Type']);
        static::assertSame('NO', $customerAddressColumns['first_name']['Null']);

        static::assertStringContainsString('varchar(255)', $customerAddressColumns['last_name']['Type']);
        static::assertSame('NO', $customerAddressColumns['last_name']['Null']);

        $orderAddressColumns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `order_address`');

        static::assertStringContainsString('varchar(255)', $orderAddressColumns['first_name']['Type']);
        static::assertSame('NO', $orderAddressColumns['first_name']['Null']);

        static::assertStringContainsString('varchar(255)', $orderAddressColumns['last_name']['Type']);
        static::assertSame('NO', $orderAddressColumns['last_name']['Null']);
    }

    public function testUpdateDestructiveIsEmpty(): void
    {
        static::expectNotToPerformAssertions();

        $migration = new Migration1756068709FixCustomerAddressNameFieldLengths();

        // Non-destructive migrations should have empty updateDestructive
        $migration->updateDestructive($this->connection);
    }
}
