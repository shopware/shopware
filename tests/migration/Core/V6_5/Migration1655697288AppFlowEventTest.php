<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_5;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_5\Migration1655697288AppFlowEvent;

/**
 * @internal
 */
#[CoversClass(Migration1655697288AppFlowEvent::class)]
class Migration1655697288AppFlowEventTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
        $migration = new Migration1655697288AppFlowEvent();
        $migration->update($this->connection);
        $migration->update($this->connection);
    }

    public function testTablesArePresent(): void
    {
        $appFlowEventColumns = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM app_flow_event'), 'Field');

        static::assertContains('id', $appFlowEventColumns);
        static::assertContains('app_id', $appFlowEventColumns);
        static::assertContains('name', $appFlowEventColumns);
        static::assertContains('aware', $appFlowEventColumns);
        static::assertContains('custom_fields', $appFlowEventColumns);
        static::assertContains('created_at', $appFlowEventColumns);
        static::assertContains('updated_at', $appFlowEventColumns);

        $flow = array_column($this->connection->fetchAllAssociative('SHOW COLUMNS FROM flow'), 'Field');
        static::assertContains('app_flow_event_id', $flow);
    }
}
