<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1775200001IncreaseProductDisplayGroupLength;

/**
 * @internal
 */
#[CoversClass(Migration1775200001IncreaseProductDisplayGroupLength::class)]
class Migration1775200001IncreaseProductDisplayGroupLengthTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = KernelLifecycleManager::getConnection();
    }

    protected function tearDown(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(64) NULL');

        parent::tearDown();
    }

    public function testMigration(): void
    {
        $this->rollback();

        $migration = new Migration1775200001IncreaseProductDisplayGroupLength();
        static::assertSame(1775200001, $migration->getCreationTimestamp());

        // make sure the migration is idempotent
        $migration->update($this->connection);
        $migration->update($this->connection);

        $column = $this->connection->fetchAssociative('SHOW COLUMNS FROM `product` LIKE :column', [
            'column' => 'display_group',
        ]);

        static::assertIsArray($column);
        static::assertSame('varchar(64)', strtolower((string) $column['Type']));
        static::assertSame('YES', $column['Null']);
        static::assertNull($column['Default']);
    }

    private function rollback(): void
    {
        $this->connection->executeStatement('ALTER TABLE `product` MODIFY `display_group` VARCHAR(50) NULL');
    }
}
