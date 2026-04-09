<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Media\Event;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Media\Event\MediaFileExtensionWhitelistEvent;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Content\Media\MediaException;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(MediaFileExtensionWhitelistEvent::class)]
class MediaFileExtensionWhitelistEventTest extends TestCase
{
    public function testGetters(): void
    {
        $whitelist = ['jpg', 'png', 'gif'];
        $context = Context::createDefaultContext();

        $event = new MediaFileExtensionWhitelistEvent($whitelist, $context);

        static::assertSame($whitelist, $event->getWhitelist());
        static::assertSame($context, $event->getContext());
    }

    public function testSetWhitelist(): void
    {
        $event = new MediaFileExtensionWhitelistEvent(['jpg'], Context::createDefaultContext());

        $newWhitelist = ['png', 'webp'];
        $event->setWhitelist($newWhitelist);

        static::assertSame($newWhitelist, $event->getWhitelist());
    }

    public function testGetContextThrowsWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new MediaFileExtensionWhitelistEvent(['jpg']);

        $this->expectException(MediaException::class);
        $event->getContext();
    }

    public function testGetNullableContextReturnsNullWithoutContext(): void
    {
        Feature::skipTestIfActive('v6.8.0.0', $this);

        $event = @new MediaFileExtensionWhitelistEvent(['jpg']);

        static::assertNull(@$event->getNullableContext());
    }
}
