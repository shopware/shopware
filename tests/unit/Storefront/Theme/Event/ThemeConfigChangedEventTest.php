<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ThemeConfigChangedEvent::class)]
class ThemeConfigChangedEventTest extends TestCase
{
    public function testGetters(): void
    {
        $themeId = Uuid::randomHex();
        $config = ['primary' => ['value' => '#000']];
        $context = Context::createDefaultContext();

        $event = new ThemeConfigChangedEvent($themeId, $config, $context);

        static::assertSame($themeId, $event->getThemeId());
        static::assertSame($config, $event->getConfig());
        static::assertSame($context, $event->getContext());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new ThemeConfigChangedEvent(Uuid::randomHex(), []);

        $this->expectException(FrameworkException::class);
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new ThemeConfigChangedEvent(Uuid::randomHex(), []);

        static::assertNull(@$event->getNullableContext());
    }
}
