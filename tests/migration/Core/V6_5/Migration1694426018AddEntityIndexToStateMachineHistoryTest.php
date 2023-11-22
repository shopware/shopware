<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1694426018AddEntityIndexToStateMachineHistory;

/**
 * @internal
 */
#[CoversClass(Migration1694426018AddEntityIndexToStateMachineHistory::class)]
class Migration1694426018AddEntityIndexToStateMachineHistoryTest extends TestCase
{
    private Connection $connection;

    private Migration1694426018AddEntityIndexToStateMachineHistory $migration;

    protected function setUp(): void
    {
        $this->migration = new Migration1694426018AddEntityIndexToStateMachineHistory();
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testGetCreationTimestamp(): void
    {
        static::assertEquals('1694426018', $this->migration->getCreationTimestamp());
    }

    public function testUpdate(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString(
            '`idx.state_machine_history.referenced_entity` (`referenced_id`,`referenced_version_id`)',
            $this->getSchema(),
        );
    }

    public function testUpdateTwice(): void
    {
        $this->migration->update($this->connection);

        static::assertStringContainsString(
            '`idx.state_machine_history.referenced_entity` (`referenced_id`,`referenced_version_id`)',
            $this->getSchema(),
        );

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
