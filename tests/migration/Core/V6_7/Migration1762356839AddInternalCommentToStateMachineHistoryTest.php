<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Framework\Util\Database\TableHelper;
use Shopware\Core\Migration\V6_7\Migration1762356839AddInternalCommentToStateMachineHistory;

/**
 * @internal
 */
#[CoversClass(Migration1762356839AddInternalCommentToStateMachineHistory::class)]
class Migration1762356839AddInternalCommentToStateMachineHistoryTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertSame(1762356839, (new Migration1762356839AddInternalCommentToStateMachineHistory())->getCreationTimestamp());
    }

    public function testCreationTimestamp(): void
    {
        $migration = new Migration1762356839AddInternalCommentToStateMachineHistory();
        static::assertSame(1762356839, $migration->getCreationTimestamp());
    }

    public function testMigration(): void
    {
        $this->rollback();
        $migration = new Migration1762356839AddInternalCommentToStateMachineHistory();
        $migration->update($this->connection);
        $migration->update($this->connection);

        $hasColumn = TableHelper::columnExists($this->connection, 'state_machine_history', 'internal_comment');
        static::assertTrue($hasColumn);
    }

    private function rollback(): void
    {
        $hasColumn = TableHelper::columnExists($this->connection, 'state_machine_history', 'internal_comment');

        if ($hasColumn) {
            $this->connection->executeStatement('ALTER TABLE `state_machine_history` DROP COLUMN `internal_comment`;');
        }
    }
}
