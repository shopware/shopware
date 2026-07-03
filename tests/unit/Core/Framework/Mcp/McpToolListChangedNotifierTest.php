<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use Mcp\Schema\Notification\ToolListChangedNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Mcp\McpToolListChangedNotifier;

/**
 * @internal
 */
#[CoversClass(McpToolListChangedNotifier::class)]
class McpToolListChangedNotifierTest extends TestCase
{
    public function testNotifyNoopsOutsideMcpRequestFiber(): void
    {
        $notifier = new McpToolListChangedNotifier();

        $notifier->notify();

        static::assertTrue(true);
    }

    public function testNotifyYieldsToolListChangedNotificationInsideFiber(): void
    {
        $notifier = new McpToolListChangedNotifier();

        $fiber = new \Fiber(static function () use ($notifier): string {
            $notifier->notify();

            return 'done';
        });

        $yielded = $fiber->start();

        static::assertIsArray($yielded);
        static::assertSame('notification', $yielded['type'] ?? null);
        static::assertInstanceOf(ToolListChangedNotification::class, $yielded['notification'] ?? null);

        $fiber->resume();

        static::assertSame('done', $fiber->getReturn());
    }
}
