<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ScheduledTask\McpToolsetSessionCleanupTask;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolsetSessionCleanupTask::class)]
class McpToolsetSessionCleanupTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('mcp_toolset_session.cleanup', McpToolsetSessionCleanupTask::getTaskName());
        // ScheduledTask::DAILY is protected; assert the resolved value (one day in seconds).
        static::assertSame(86400, McpToolsetSessionCleanupTask::getDefaultInterval());
        static::assertTrue(McpToolsetSessionCleanupTask::shouldRescheduleOnFailure());
    }
}
