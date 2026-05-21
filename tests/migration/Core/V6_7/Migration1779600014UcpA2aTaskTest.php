<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1779600014UcpA2aTask;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(Migration1779600014UcpA2aTask::class)]
class Migration1779600014UcpA2aTaskTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1779600014, (new Migration1779600014UcpA2aTask())->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        static::assertFalse(TableHelper::tableExists($this->connection, 'ucp_a2a_task'));

        $migration = new Migration1779600014UcpA2aTask();
        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue(TableHelper::tableExists($this->connection, 'ucp_a2a_task'));

        foreach ([
            'id',
            'sales_channel_id',
            'message_id',
            'task_id',
            'context_id',
            'state',
            'message_response',
            'created_at',
            'expires_at',
        ] as $column) {
            static::assertTrue(
                TableHelper::columnExists($this->connection, 'ucp_a2a_task', $column),
                \sprintf('Column "%s" is missing from ucp_a2a_task', $column),
            );
        }

        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_a2a_task', 'uniq.ucp_a2a_task.sc_msg'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_a2a_task', 'idx.ucp_a2a_task.task_id'));
        static::assertTrue(TableHelper::indexExists($this->connection, 'ucp_a2a_task', 'idx.ucp_a2a_task.expires_at'));
    }

    private function rollback(): void
    {
        if (TableHelper::tableExists($this->connection, 'ucp_a2a_task')) {
            $this->connection->executeStatement('DELETE FROM `ucp_a2a_task`');
        }
        $this->connection->executeStatement('DROP TABLE IF EXISTS `ucp_a2a_task`');
    }
}
