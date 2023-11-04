<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1653385302AddHeadlineColumnToAppFlowActionTable;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1653385302AddHeadlineColumnToAppFlowActionTable
 */
class Migration1653385302AddHeadlineColumnToAppFlowActionTableTest extends TestCase
{
    private Connection $connection;

    private Migration1653385302AddHeadlineColumnToAppFlowActionTable $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = KernelLifecycleManager::getConnection();
        $this->migration = new Migration1653385302AddHeadlineColumnToAppFlowActionTable();
        $this->prepare();
    }

    public function testMigration(): void
    {
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'app_flow_action_translation', 'headline'));

        $this->migration->update($this->connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'app_flow_action_translation', 'headline'));
    }

    private function prepare(): void
    {
        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'app_flow_action_translation', 'headline')) {
            $this->connection->executeStatement('ALTER TABLE `app_flow_action_translation` DROP COLUMN `headline`');
        }
    }
}
