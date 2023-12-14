<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1673426317ImproveStateMachineHistoryQueryPerformance;

/**
 * @internal
 */
#[CoversClass(Migration1673426317ImproveStateMachineHistoryQueryPerformance::class)]
class Migration1673426317ImproveStateMachineHistoryQueryPerformanceTest extends TestCase
{
    private Connection $connection;

    private Migration1673426317ImproveStateMachineHistoryQueryPerformance $migration;

    protected function setUp(): void
    {
        $this->migration = new Migration1673426317ImproveStateMachineHistoryQueryPerformance();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1673426317', $this->migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString('`referenced_id` binary(16)', $this->getSchema());
        static::assertStringContainsString('`referenced_version_id` binary(16)', $this->getSchema());
    }

    public function testUpdateTwice(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString('`referenced_id` binary(16)', $this->getSchema());
        static::assertStringContainsString('`referenced_version_id` binary(16)', $this->getSchema());

        $expected = $this->getSchema();

        $this->migration->update($this->connection);
        static::assertSame($expected, $this->getSchema());
    }

    /**
     * @throws \Throwable
     */
    private function getSchema(): string
    {
        $schema = $this->connection->fetchAssociative(sprintf('SHOW CREATE TABLE `%s`', 'state_machine_history'));
        static::assertNotFalse($schema);
        static::assertIsString($schema['Create Table']);

        return $schema['Create Table'];
    }
}
