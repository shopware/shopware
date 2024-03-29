<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cleanup\CleanupCartTask;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1678801126AddScheduledTaskDefaultRunIntervalColumn;

/**
 * @internal
 */
#[CoversClass(Migration1678801126AddScheduledTaskDefaultRunIntervalColumn::class)]
class Migration1678801126AddScheduledTaskDefaultRunIntervalColumnTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();

        try {
            $this->connection->executeStatement(
                'ALTER TABLE `scheduled_task` DROP COLUMN `default_run_interval`;'
            );
        } catch (\Throwable) {
        }
    }

    public function testUpdate(): void
    {
        $migration = new Migration1678801126AddScheduledTaskDefaultRunIntervalColumn();

        $migration->update($this->connection);
        $migration->update($this->connection);

        $defaultInterval = $this->connection->fetchOne(
            'SELECT `default_run_interval` FROM `scheduled_task` WHERE `name` = "cart.cleanup";',
        );
        static::assertEquals(CleanupCartTask::getDefaultInterval(), $defaultInterval);
    }
}
