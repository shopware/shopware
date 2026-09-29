<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Notification;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Notification\McpListChangedNotificationSet;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpListChangedNotificationSet::class)]
class McpListChangedNotificationSetTest extends TestCase
{
    public function testNoneContainsNoChanges(): void
    {
        $set = McpListChangedNotificationSet::none();

        static::assertFalse($set->tools);
        static::assertFalse($set->resources);
        static::assertFalse($set->prompts);
        static::assertFalse($set->hasChanges());
    }

    public function testMergeKeepsAllChangedLists(): void
    {
        $set = new McpListChangedNotificationSet(tools: true, resources: false, prompts: false);
        $merged = $set->merge(new McpListChangedNotificationSet(tools: false, resources: true, prompts: true));

        static::assertTrue($merged->tools);
        static::assertTrue($merged->resources);
        static::assertTrue($merged->prompts);
        static::assertTrue($merged->hasChanges());
    }
}
