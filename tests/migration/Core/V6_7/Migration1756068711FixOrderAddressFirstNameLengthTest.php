<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1756068711FixOrderAddressFirstNameLength;

/**
 * @internal
 */
#[CoversClass(Migration1756068711FixOrderAddressFirstNameLength::class)]
class Migration1756068711FixOrderAddressFirstNameLengthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        $migration = new Migration1756068711FixOrderAddressFirstNameLength();
        static::assertSame(1756068711, $migration->getCreationTimestamp());
    }

    public function testMigrationChangesColumnLengthAndIsIdempotent(): void
    {
        $migration = new Migration1756068711FixOrderAddressFirstNameLength();

        // Set column to original size to test the migration properly, as test DB may already have VARCHAR(255)
        $this->connection->executeStatement('
            ALTER TABLE `order_address`
            MODIFY COLUMN `first_name` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL
        ');

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `order_address`');
        static::assertStringContainsString('varchar(50)', $columns['first_name']['Type']);

        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `order_address`');
        static::assertStringContainsString('varchar(255)', $columns['first_name']['Type']);
        static::assertSame('NO', $columns['first_name']['Null']);

        $migration->update($this->connection);

        $columns = $this->connection->fetchAllAssociativeIndexed('SHOW COLUMNS FROM `order_address`');
        static::assertStringContainsString('varchar(255)', $columns['first_name']['Type']);
        static::assertSame('NO', $columns['first_name']['Null']);
    }
}
