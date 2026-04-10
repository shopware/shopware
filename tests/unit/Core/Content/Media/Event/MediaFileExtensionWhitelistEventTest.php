<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Feature\FeatureException;

/**
 * @internal
 */
#[CoversClass(MediaFileExtensionWhitelistEvent::class)]
class MediaFileExtensionWhitelistEventTest extends TestCase
{
    public function testConstructorRequiresContextWhenFeatureActive(): void
    {
        Feature::skipTestIfInActive('v6.8.0.0', $this);

        $this->expectException(FeatureException::class);
        new MediaFileExtensionWhitelistEvent(['jpg']);
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new MediaFileExtensionWhitelistEvent(['jpg']);

        $this->expectExceptionObject(MediaException::invalidEventData('No context provided. Pass $context to the constructor of ' . MediaFileExtensionWhitelistEvent::class));
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = new MediaFileExtensionWhitelistEvent(['jpg']);

        static::assertNull($event->getNullableContext());
    }
}
