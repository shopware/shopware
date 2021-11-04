<?php declare(strict_types=1);

namespace Shopware\Core\Migration\Test;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTask;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Migration\V6_4\Migration1633347511ChangeProductExportInterval;

class Migration1633347511ChangeProductExportIntervalTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const CUSTOM_INTERVAL = 120;

    private Connection $connection;

    private Migration1633347511ChangeProductExportInterval $migration;

    protected function setUp(): void
    {
        $this->connection = $this->getContainer()->get(Connection::class);
        $this->migration = new Migration1633347511ChangeProductExportInterval();
    }

    public function testMigrationOverridesInterval(): void
    {
        $this->migration->update($this->connection);
        static::assertSame(ProductExportGenerateTask::getDefaultInterval(), $this->getInterval());

        $this->setInterval(self::CUSTOM_INTERVAL);
        $this->migration->update($this->connection);
        static::assertSame(self::CUSTOM_INTERVAL, $this->getInterval());
    }

    private function setInterval(int $interval): void
    {
        $this->connection->update(
            'scheduled_task',
            ['run_interval' => $interval],
            ['name' => ProductExportGenerateTask::getTaskName()]
        );
    }

    private function getInterval(): int
    {
        $statement = sprintf(
            'SELECT run_interval FROM scheduled_task WHERE `name` = "%s"',
            ProductExportGenerateTask::getTaskName()
        );

        return (int) $this->connection->fetchOne($statement);
    }
}
