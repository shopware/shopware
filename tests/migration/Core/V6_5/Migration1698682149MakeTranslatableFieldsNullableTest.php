<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1698682149MakeTranslatableFieldsNullable;

/**
 * @internal
 */
#[CoversClass(Migration1698682149MakeTranslatableFieldsNullable::class)]
class Migration1698682149MakeTranslatableFieldsNullableTest extends TestCase
{
    public function testUpdatesColumns(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1698682149MakeTranslatableFieldsNullable();
        $migration->update($connection);

        foreach ($migration->toUpdate as $tableName => $columns) {
            $this->assertColumnsAreNullable($connection, $tableName, $columns);
        }
    }

    public function testMultipleExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1698682149MakeTranslatableFieldsNullable();
        $migration->update($connection);
        $migration->update($connection);

        foreach ($migration->toUpdate as $tableName => $columns) {
            $this->assertColumnsAreNullable($connection, $tableName, $columns);
        }
    }

    public function testPartialExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1698682149MakeTranslatableFieldsNullable();

        // We set some to nullable already
        $connection->executeStatement('ALTER TABLE `app_translation` MODIFY `label` VARCHAR(255) DEFAULT NULL;');
        $connection->executeStatement('ALTER TABLE `app_action_button_translation` MODIFY `label` VARCHAR(255) DEFAULT NULL;');
        $connection->executeStatement('ALTER TABLE `app_script_condition_translation` MODIFY `name` VARCHAR(255) DEFAULT NULL;');
        $connection->executeStatement('ALTER TABLE `app_cms_block_translation` MODIFY `label` VARCHAR(255) DEFAULT NULL;');

        $migration->update($connection);

        foreach ($migration->toUpdate as $tableName => $columns) {
            $this->assertColumnsAreNullable($connection, $tableName, $columns);
        }
    }

    /**
     * @param string[] $columns
     */
    private function assertColumnsAreNullable(Connection $connection, string $table, array $columns): void
    {
        foreach ($columns as $column) {
            static::assertTrue(EntityDefinitionQueryHelper::columnIsNullable($connection, $table, $column));
        }
    }
}
