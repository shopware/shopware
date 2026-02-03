<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\CancellationRequest\Event\CancellationRequestEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1768545321CancellationRequestFlow;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1768545321CancellationRequestFlow::class)]
class Migration1768545321CancellationRequestFlowTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        $migration = new Migration1768545321CancellationRequestFlow();

        static::assertTrue($this->hasFlowEntry(CancellationRequestEvent::EVENT_NAME));
        $this->dropFlowEntry(CancellationRequestEvent::EVENT_NAME);

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->hasFlowEntry(CancellationRequestEvent::EVENT_NAME));
    }

    private function hasFlowEntry(string $eventName): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `flow` WHERE `event_name` = :name',
            ['name' => $eventName]
        );

        return !empty($result);
    }

    private function dropFlowEntry(string $eventName): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `flow` WHERE `event_name` = :name',
            ['name' => $eventName]
        );

        static::assertFalse($this->hasFlowEntry(CancellationRequestEvent::EVENT_NAME));
    }
}
