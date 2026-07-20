<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Webhook\Hookable;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Webhook\Hookable\HookableEventDescription;

/**
 * @internal
 */
#[CoversClass(HookableEventDescription::class)]
class HookableEventDescriptionTest extends TestCase
{
    public function testConstructorAssignsProperties(): void
    {
        $description = new HookableEventDescription('test.event', 'Test description', ['test:read']);

        static::assertSame('test.event', $description->eventName);
        static::assertSame('Test description', $description->description);
        static::assertSame(['test:read'], $description->privileges);
    }
}
