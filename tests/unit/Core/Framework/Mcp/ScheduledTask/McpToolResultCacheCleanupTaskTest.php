<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\ScheduledTask;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\ScheduledTask\McpToolResultCacheCleanupTask;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpToolResultCacheCleanupTask::class)]
class McpToolResultCacheCleanupTaskTest extends TestCase
{
    public function testTaskMetadata(): void
    {
        static::assertSame('mcp_tool_result_cache.cleanup', McpToolResultCacheCleanupTask::getTaskName());
        // ScheduledTask::HOURLY is protected; assert the resolved value (one hour in seconds).
        static::assertSame(3600, McpToolResultCacheCleanupTask::getDefaultInterval());
        static::assertTrue(McpToolResultCacheCleanupTask::shouldRescheduleOnFailure());
    }
}
