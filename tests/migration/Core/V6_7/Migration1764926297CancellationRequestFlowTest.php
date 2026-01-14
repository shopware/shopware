<?php declare(strict_types=1);

namespace Shopware\Tests\Migration\Core\V6_7;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\CancellationRequest\Event\CancellationRequestEvent;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Test\TestCaseBase\KernelLifecycleManager;
use Shopware\Core\Migration\V6_7\Migration1764926297CancellationRequestFlow;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(Migration1764926297CancellationRequestFlow::class)]
class Migration1764926297CancellationRequestFlowTest extends TestCase
{
    private Connection $connection;

    protected function setUp(): void
    {
        $this->connection = KernelLifecycleManager::getConnection();
    }

    public function testUpdate(): void
    {
        $migration = new Migration1764926297CancellationRequestFlow();

        static::assertTrue($this->hasFlowEntry());
        $this->dropFlowEntry();

        $migration->update($this->connection);
        $migration->update($this->connection);

        static::assertTrue($this->hasFlowEntry());
    }

    private function hasFlowEntry(): bool
    {
        $result = $this->connection->fetchOne(
            'SELECT `id` FROM `flow` WHERE `event_name` = :name',
            ['name' => CancellationRequestEvent::EVENT_NAME]
        );

        return !empty($result);
    }

    private function dropFlowEntry(): void
    {
        $this->connection->executeStatement(
            'DELETE FROM `flow` WHERE `event_name` = :name',
            ['name' => CancellationRequestEvent::EVENT_NAME]
        );

        static::assertFalse($this->hasFlowEntry());
    }
}