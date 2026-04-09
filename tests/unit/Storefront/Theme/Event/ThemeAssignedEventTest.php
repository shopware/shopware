<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeAssignedEvent::class)]
class ThemeAssignedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $themeId = Uuid::randomHex();
        $salesChannelId = Uuid::randomHex();
        $context = Context::createDefaultContext();

        $event = new ThemeAssignedEvent($themeId, $salesChannelId, $context);

        static::assertSame($themeId, $event->getThemeId());
        static::assertSame($salesChannelId, $event->getSalesChannelId());
        static::assertSame($context, $event->getContext());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new ThemeAssignedEvent(Uuid::randomHex(), Uuid::randomHex());

        $this->expectException(FrameworkException::class);
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new ThemeAssignedEvent(Uuid::randomHex(), Uuid::randomHex());

        static::assertNull(@$event->getNullableContext());
    }
}
