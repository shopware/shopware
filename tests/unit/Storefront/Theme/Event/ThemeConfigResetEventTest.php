<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Theme\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Framework\FrameworkException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Annotation\DisabledFeatures;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;

/**
 * @internal
 */
#[CoversClass(ThemeConfigResetEvent::class)]
class ThemeConfigResetEventTest extends TestCase
{
    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new ThemeConfigResetEvent(Uuid::randomHex(), $context);

        static::assertSame($context, $event->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsContextWhenFeatureInactiveAndContextProvided(): void
    {
        $context = Context::createDefaultContext();
        $event = new ThemeConfigResetEvent(Uuid::randomHex(), $context);

        static::assertSame($context, $event->getNullableContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        $event = new ThemeConfigResetEvent(Uuid::randomHex());

        static::assertNull($event->getNullableContext());
    }

    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $context to ' . ThemeConfigResetEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new ThemeConfigResetEvent(Uuid::randomHex());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetContextThrowsWithoutContext(): void
    {
        $event = new ThemeConfigResetEvent(Uuid::randomHex());

        $this->expectExceptionObject(FrameworkException::invalidEventData('No context provided. Pass $context to the constructor of ' . ThemeConfigResetEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextThrowsWhenFeatureActive(): void
    {
        $event = new ThemeConfigResetEvent(Uuid::randomHex(), Context::createDefaultContext());

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: getNullableContext() is deprecated, use getContext() instead.'));
        $event->getNullableContext();
    }
}
