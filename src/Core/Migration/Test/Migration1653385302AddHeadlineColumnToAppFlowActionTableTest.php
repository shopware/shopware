<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1653385302AddHeadlineColumnToAppFlowActionTable;

/**
 * @internal
 */
class Migration1653385302AddHeadlineColumnToAppFlowActionTableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1653385302AddHeadlineColumnToAppFlowActionTable $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
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
