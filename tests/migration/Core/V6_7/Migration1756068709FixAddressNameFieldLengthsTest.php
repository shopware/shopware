<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1756068709FixCustomerAddressFirstNameLength;
use Shopware\Core\Migration\V6_7\Migration1756068710FixCustomerAddressLastNameLength;
use Shopware\Core\Migration\V6_7\Migration1756068711FixOrderAddressFirstNameLength;
use Shopware\Core\Migration\V6_7\Migration1756068712FixOrderAddressLastNameLength;

/**
 * @internal
 */
#[CoversClass(Migration1756068709FixCustomerAddressFirstNameLength::class)]
#[CoversClass(Migration1756068710FixCustomerAddressLastNameLength::class)]
#[CoversClass(Migration1756068711FixOrderAddressFirstNameLength::class)]
#[CoversClass(Migration1756068712FixOrderAddressLastNameLength::class)]
class Migration1756068709FixAddressNameFieldLengthsTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testCustomerAddressFirstNameMigration(): void
    {
        $migration = new Migration1756068709FixCustomerAddressFirstNameLength();
        static::assertSame(1756068709, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `customer_address`');

        static::assertStringContainsString('varchar(255)', $columns['first_name']['Type']);
        static::assertSame('NO', $columns['first_name']['Null']);
    }

    public function testCustomerAddressLastNameMigration(): void
    {
        $migration = new Migration1756068710FixCustomerAddressLastNameLength();
        static::assertSame(1756068710, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `customer_address`');

        static::assertStringContainsString('varchar(255)', $columns['last_name']['Type']);
        static::assertSame('NO', $columns['last_name']['Null']);
    }

    public function testOrderAddressFirstNameMigration(): void
    {
        $migration = new Migration1756068711FixOrderAddressFirstNameLength();
        static::assertSame(1756068711, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `order_address`');

        static::assertStringContainsString('varchar(255)', $columns['first_name']['Type']);
        static::assertSame('NO', $columns['first_name']['Null']);
    }

    public function testOrderAddressLastNameMigration(): void
    {
        $migration = new Migration1756068712FixOrderAddressLastNameLength();
        static::assertSame(1756068712, $migration->getCreationTimestamp());

        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `order_address`');

        static::assertStringContainsString('varchar(255)', $columns['last_name']['Type']);
        static::assertSame('NO', $columns['last_name']['Null']);
    }
}
