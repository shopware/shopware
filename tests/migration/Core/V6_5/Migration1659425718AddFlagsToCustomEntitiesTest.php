<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1659425718AddFlagsToCustomEntities;

/**
 * @package content
 *
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_5\Migration1659425718AddFlagsToCustomEntities
 */
class Migration1659425718AddFlagsToCustomEntitiesTest extends TestCase
{
    public function testMultipleExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1659425718AddFlagsToCustomEntities();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'custom_entity', 'flags'));
    }

    public function testColumnGetsCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1659425718AddFlagsToCustomEntities();

        if (EntityDefinitionQueryHelper::columnExists($connection, 'custom_entity', 'flags')) {
            $connection->executeStatement('ALTER TABLE `custom_entity` DROP `flags`;');
        }

        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'custom_entity', 'flags'));
    }
}
