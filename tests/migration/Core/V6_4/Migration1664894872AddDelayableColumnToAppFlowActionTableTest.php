<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Dbal\EntityDefinitionQueryHelper;
use Shopware\Core\Framework\Test\TestCaseBase\KernelTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1664894872AddDelayableColumnToAppFlowActionTable;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1664894872AddDelayableColumnToAppFlowActionTable
 */
class Migration1664894872AddDelayableColumnToAppFlowActionTableTest extends TestCase
{
    use KernelTestBehaviour;

    private Connection $connection;

    private Migration1664894872AddDelayableColumnToAppFlowActionTable $migration;

    protected function setUp(): void
    {
        parent::setUp();
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->migration = new Migration1664894872AddDelayableColumnToAppFlowActionTable();
        $this->prepare();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals(1664894872, $this->migration->getCreationTimestamp());
    }

    public function testMigrationOnceOrMultipleTimes(): void
    {
        static::assertFalse(EntityDefinitionQueryHelper::columnExists($this->connection, 'app_flow_action', 'delayable'));

        $this->migration->update($this->connection);
        $this->migration->update($this->connection);

        static::assertTrue(EntityDefinitionQueryHelper::columnExists($this->connection, 'app_flow_action', 'delayable'));
    }

    private function prepare(): void
    {
        if (EntityDefinitionQueryHelper::columnExists($this->connection, 'app_flow_action', 'delayable')) {
            $this->connection->executeStatement('ALTER TABLE `app_flow_action` DROP COLUMN `delayable`');
        }
    }
}
