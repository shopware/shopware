<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1756068710FixCustomerAddressLastNameLength;

/**
 * @internal
 */
#[CoversClass(Migration1756068710FixCustomerAddressLastNameLength::class)]
class Migration1756068710FixCustomerAddressLastNameLengthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1756068710FixCustomerAddressLastNameLength();
        static::assertSame(1756068710, $migration->getCreationTimestamp());
    }

    public function testUpdateChangesLastNameColumnLength(): void
    {
        $migration = new Migration1756068710FixCustomerAddressLastNameLength();

        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `customer_address`');

        static::assertStringContainsString('varchar(255)', $columns['last_name']['Type']);
        static::assertSame('NO', $columns['last_name']['Null']);
    }

    public function testUpdateIsIdempotent(): void
    {
        $migration = new Migration1756068710FixCustomerAddressLastNameLength();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `customer_address`');

        static::assertStringContainsString('varchar(255)', $columns['last_name']['Type']);
        static::assertSame('NO', $columns['last_name']['Null']);
    }
}
