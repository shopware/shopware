<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;

/**
 * @internal
 */
#[CoversClass(ThemeAssignedEvent::class)]
class ThemeAssignedEventTest extends TestCase
{
    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->expectException(FeatureException::class);
        new ThemeAssignedEvent(Uuid::randomHex(), Uuid::randomHex());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new ThemeAssignedEvent(Uuid::randomHex(), Uuid::randomHex());

        $this->expectExceptionObject(FrameworkException::invalidEventData('No context provided. Pass $context to the constructor of ' . ThemeAssignedEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new ThemeAssignedEvent(Uuid::randomHex(), Uuid::randomHex());

        static::assertNull($event->getNullableContext());
    }
}
