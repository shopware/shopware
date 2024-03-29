<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Column;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1662533751AddCustomEntityTypeIdToCategory;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(Migration1662533751AddCustomEntityTypeIdToCategory::class)]
class Migration1662533751AddCustomEntityTypeIdToCategoryTest extends TestCase
{
    public function testGetCreationTimestamp(): void
    {
        $expectedTimestamp = 1662533751;
        $migration = new Migration1662533751AddCustomEntityTypeIdToCategory();

        static::assertEquals($expectedTimestamp, $migration->getCreationTimestamp());
    }

    public function testMultipleExecutions(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1662533751AddCustomEntityTypeIdToCategory();

        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'category', 'custom_entity_type_id'));
    }

    public function testColumnGetsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1662533751AddCustomEntityTypeIdToCategory();

        $keyExists = $this->keyExists($connection, 'fk.category.custom_entity_type_id');
        if ($keyExists) {
            $connection->executeStatement('ALTER TABLE `category` DROP FOREIGN KEY `fk.category.custom_entity_type_id`;');
        }

        $columnExists = $this->hasColumn($connection, 'custom_entity_type_id');
        if ($columnExists) {
            $connection->executeStatement('ALTER TABLE `category` DROP COLUMN `custom_entity_type_id`;');
        }

        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'category', 'custom_entity_type_id'));
    }

    private function hasColumn(Connection $connection, string $columnName): bool
    {
        return array_filter(
            $connection->createSchemaManager()->listTableColumns('category'),
            static fn (Column $column): bool => $column->getName() === $columnName
        ) !== [];
    }

    private function keyExists(Connection $connection, string $keyName): bool
    {
        return $connection->executeQuery(
            'SELECT *
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = "category"
              AND CONSTRAINT_NAME = :keyName;',
            ['keyName' => $keyName]
        )->fetchOne() !== false;
    }
}
