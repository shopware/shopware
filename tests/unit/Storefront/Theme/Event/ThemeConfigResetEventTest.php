<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;

/**
 * @internal
 */
#[CoversClass(ThemeConfigResetEvent::class)]
class ThemeConfigResetEventTest extends TestCase
{
    public function testGetContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new ThemeConfigResetEvent(Uuid::randomHex());

        static::assertNull($event->getContext());
    }
}
