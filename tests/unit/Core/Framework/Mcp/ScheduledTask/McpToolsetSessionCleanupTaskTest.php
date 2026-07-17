<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTask;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTask;

/**
 * @internal
 */
#[CoversClass(McpToolsetSessionCleanupTask::class)]
class McpToolsetSessionCleanupTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('mcp_toolset_session.cleanup', McpToolsetSessionCleanupTask::getTaskName());
        static::assertSame(ScheduledTask::DAILY, McpToolsetSessionCleanupTask::getDefaultInterval());
        static::assertTrue(McpToolsetSessionCleanupTask::shouldRescheduleOnFailure());
    }
}
