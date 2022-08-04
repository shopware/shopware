<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1659425718AddFlagsToCustomEntities;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1659425718AddFlagsToCustomEntities
 */
class Migration1659425718AddFlagsToCustomEntitiesTest extends TestCase
{
    public function testMultipleExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1659425718AddFlagsToCustomEntities();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->columnExists($connection, 'flags'));
        static::assertTrue($this->columnExists($connection, 'flag_config'));
    }

    public function testColumnGetsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1659425718AddFlagsToCustomEntities();

        if ($this->columnExists($connection, 'flags')) {
            $connection->executeStatement('ALTER TABLE `custom_entity` DROP `flags`;');
        }

        if ($this->columnExists($connection, 'flag_config')) {
            $connection->executeStatement('ALTER TABLE `custom_entity` DROP `flag_config`;');
        }

        $migration->update($connection);

        static::assertTrue($this->columnExists($connection, 'flags'));
        static::assertTrue($this->columnExists($connection, 'flag_config'));
    }

    private function columnExists(Connection $connection, string $column): bool
    {
        $field = $connection->fetchOne(
            'SHOW COLUMNS FROM `custom_entity` WHERE `Field` LIKE :column;',
            ['column' => $column]
        );

        return $field === $column;
    }
}
