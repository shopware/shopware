<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1666689977AddPluginIdToCustomEntity;

/**
 * @package content
 *
 * @internal
 */
#[CoversClass(Migration1666689977AddPluginIdToCustomEntity::class)]
class Migration1666689977AddPluginIdToCustomEntityTest extends TestCase
{
    public function testMultipleExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1666689977AddPluginIdToCustomEntity();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->columnExists($connection, 'plugin_id'));
    }

    public function testColumnGetsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1666689977AddPluginIdToCustomEntity();

        $keyExists = $this->keyExists($connection, 'fk.custom_entity.plugin_id');
        if ($keyExists) {
            $connection->executeStatement('ALTER TABLE `custom_entity` DROP FOREIGN KEY `fk.custom_entity.plugin_id`;');
        }

        if ($this->columnExists($connection, 'plugin_id')) {
            $connection->executeStatement('ALTER TABLE `custom_entity` DROP `plugin_id`;');
        }
        $migration->update($connection);

        static::assertTrue($this->columnExists($connection, 'plugin_id'));
    }

    private function columnExists(Connection $connection, string $column): bool
    {
        $field = $connection->fetchOne(
            'SHOW COLUMNS FROM `custom_entity` WHERE `Field` LIKE :column;',
            ['column' => $column]
        );

        return $field === $column;
    }

    private function keyExists(Connection $connection, string $keyName): bool
    {
        $sql = 'SELECT *
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = "custom_entity"
                AND CONSTRAINT_NAME = :keyName;';

        return $connection->executeQuery($sql, ['keyName' => $keyName])->fetchOne() !== false;
    }
}
