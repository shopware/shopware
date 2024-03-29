<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1698919811AddDeletedAtToCustomEntity;

/**
 * @internal
 */
#[CoversClass(Migration1698919811AddDeletedAtToCustomEntity::class)]
class Migration1698919811AddDeletedAtToCustomEntityTest extends TestCase
{
    public function testMultipleExecution(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        $migration = new Migration1698919811AddDeletedAtToCustomEntity();
        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'custom_entity', 'deleted_at'));
    }

    public function testColumnsGetCreated(): void
    {
        $connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1698919811AddDeletedAtToCustomEntity();

        if (EntityDefinitionQueryHelper::columnExists($connection, 'custom_entity', 'deleted_at')) {
            $connection->executeStatement('ALTER TABLE `custom_entity` DROP `deleted_at`;');
        }

        $migration->update($connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($connection, 'custom_entity', 'deleted_at'));
    }
}
