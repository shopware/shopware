<?php declare(strict_types=1);

namespace Shopware\Tests\DevOps\Core\DevOps\StaticAnalyse\TwigStan;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Event\StorefrontRenderEvent;

/**
 * @internal
 */
#[Package('framework')]
class RenderEventListenersTest extends TestCase
{
    public function testDiscoversCompiledRegistrationsWithoutExecutingListeners(): void
    {
        $listeners = RenderEventListeners::discover(__DIR__ . '/fixtures/render-event-container.xml');

        static::assertCount(2, $listeners);
        static::assertSame(StorefrontRenderEvent::class, $listeners[0]['event']);
        static::assertSame(RenderListenerFixture::class . '::onRender', $listeners[0]['callback']);
        static::assertSame('renderEvent', $listeners[0]['parameter']);
        static::assertSame(__FILE__, $listeners[0]['file']);
        static::assertSame(100, $listeners[0]['priority']);
        static::assertSame('frontend.example.render', $listeners[1]['event']);
        static::assertSame([0, 1], array_column($listeners, 'order'));
    }
}

/**
 * @internal
 */
#[Package('framework')]
class RenderListenerFixture
{
    public function __construct()
    {
        throw new \LogicException('Listener discovery must not instantiate services.');
    }

    public function onRender(StorefrontRenderEvent $renderEvent): void
    {
        throw new \LogicException('Listener discovery must not dispatch the event.');
    }
}
