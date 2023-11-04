<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_4;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_4\Migration1664541794AddIndexForLogEntryTask;

/**
 * @internal
 *
 * @covers \Shopware\Core\Migration\V6_4\Migration1664541794AddIndexForLogEntryTask
 */
class Migration1664541794AddIndexForLogEntryTaskTest extends TestCase
{
    protected function setUp(): void
    {
        $connection = KernelLifecycleManager::getConnection();

        if ($this->indexExists($connection)) {
            $connection->executeStatement('DROP INDEX `idx.log_entry.created_at` ON `log_entry`');
        }
    }

    public function testHasCorrectTimestamp(): void
    {
        static::assertStringContainsString(
            (string) (new Migration1664541794AddIndexForLogEntryTask())->getCreationTimestamp(),
            Migration1664541794AddIndexForLogEntryTask::class
        );
    }

    public function testAddsIndexToOrderTable(): void
    {
        $migration = new Migration1664541794AddIndexForLogEntryTask();
        $connection = KernelLifecycleManager::getConnection();

        static::assertFalse($this->indexExists($connection));

        $migration->update($connection);

        static::assertTrue($this->indexExists($connection));
    }

    public function testCanBeExecutedMultipleTimes(): void
    {
        $migration = new Migration1664541794AddIndexForLogEntryTask();
        $connection = KernelLifecycleManager::getConnection();

        static::assertFalse($this->indexExists($connection));

        $migration->update($connection);
        $migration->update($connection);

        static::assertTrue($this->indexExists($connection));
    }

    private function indexExists(Connection $connection): bool
    {
        $index = $connection->executeQuery(
            'SHOW INDEXES FROM `log_entry` WHERE key_name = :indexName',
            ['indexName' => 'idx.log_entry.created_at']
        )->fetchOne();

        return $index !== false;
    }
}
