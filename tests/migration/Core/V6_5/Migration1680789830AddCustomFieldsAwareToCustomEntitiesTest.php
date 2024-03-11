<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1680789830AddCustomFieldsAwareToCustomEntities;

/**
 * @internal
 */
#[CoversClass(Migration1680789830AddCustomFieldsAwareToCustomEntities::class)]
class Migration1680789830AddCustomFieldsAwareToCustomEntitiesTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `custom_entity` DROP COLUMN `custom_fields_aware`;'
            );
            $this->connection->executeStatement(
                'ALTER TABLE `custom_entity` DROP COLUMN `label_property`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1680789830AddCustomFieldsAwareToCustomEntities();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'custom_entity', 'custom_fields_aware'));
        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'custom_entity', 'label_property'));
    }
}
