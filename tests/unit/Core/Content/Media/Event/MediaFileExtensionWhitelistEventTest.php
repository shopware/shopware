<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature\FeatureException;
use Shopware\Core\Test\Annotation\DisabledFeatures;

/**
 * @internal
 */
#[CoversClass(MediaFileExtensionWhitelistEvent::class)]
class MediaFileExtensionWhitelistEventTest extends TestCase
{
    public function testGetContextReturnsPassedContext(): void
    {
        $context = Context::createDefaultContext();
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], $context);

        static::assertSame($context, $event->getContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsContextWhenFeatureInactiveAndContextProvided(): void
    {
        $context = Context::createDefaultContext();
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], $context);

        static::assertSame($context, $event->getNullableContext());
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        $event = new MediaFileExtensionWhitelistEvent(['jpg']);

        static::assertNull($event->getNullableContext());
    }

    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        $this->expectExceptionObject(FeatureException::error(
            'Tried to access deprecated functionality: Not passing $context to ' . MediaFileExtensionWhitelistEvent::class . ' is deprecated and will be required in v6.8.0.'
        ));
        new MediaFileExtensionWhitelistEvent(['jpg']);
    }

    #[DisabledFeatures(['v6.8.0.0'])]
    public function testGetContextThrowsWithoutContext(): void
    {
        $event = new MediaFileExtensionWhitelistEvent(['jpg']);

        $this->expectExceptionObject(MediaException::invalidEventData('No context provided. Pass $context to the constructor of ' . MediaFileExtensionWhitelistEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextThrowsWhenFeatureActive(): void
    {
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], Context::createDefaultContext());

        $this->expectExceptionObject(FeatureException::error('Tried to access deprecated functionality: getNullableContext() is deprecated, use getContext() instead.'));
        $event->getNullableContext();
    }
}
