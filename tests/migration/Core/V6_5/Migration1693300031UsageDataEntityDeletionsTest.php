<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1693300031UsageDataEntityDeletions;

/**
 * @internal
 */
#[CoversClass(Migration1693300031UsageDataEntityDeletions::class)]
class Migration1693300031UsageDataEntityDeletionsTest extends TestCase
{
    public function testMigrationReturnsCorrectTimeStamp(): void
    {
        static::assertEquals(1693300031, (new Migration1693300031UsageDataEntityDeletions())->getCreationTimestamp());
    }

    public function testMigrationCanBeRunTwice(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1693300031UsageDataEntityDeletions();
        $migration->update($connection);

        $this->assertDeletionsTableExistsAndHasAllColumns($connection);

        $migration = new Migration1693300031UsageDataEntityDeletions();
        $migration->update($connection);

        $this->assertDeletionsTableExistsAndHasAllColumns($connection);
    }

    public function testDeletionTableIsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $connection->executeStatement('DROP TABLE IF EXISTS `usage_data_entity_deletion`;');
        $this->assertDeletionsTableDoesNotExist($connection);

        $migration = new Migration1693300031UsageDataEntityDeletions();
        $migration->update($connection);

        $this->assertDeletionsTableExistsAndHasAllColumns($connection);
    }

    private function assertDeletionsTableExistsAndHasAllColumns(Connection $connection): void
    {
        self::assertColumnsExists(
            $connection,
            'usage_data_entity_deletion',
            ['id', 'entity_ids', 'entity_name', 'deleted_at']
        );
    }

    private function assertDeletionsTableDoesNotExist(Connection $connection): void
    {
        static::assertFalse(EntityDefinitionQueryHelper::tableExists($connection, 'usage_data_entity_deletion'));
    }

    /**
     * @param string[] $columns
     */
    private static function assertColumnsExists(Connection $connection, string $table, array $columns): void
    {
        $data = array_column(
            $connection->fetchAllAssociative('SHOW COLUMNS FROM ' . EntityDefinitionQueryHelper::escape($table)),
            'Field'
        );

        static::assertSame($columns, $data);
    }
}
