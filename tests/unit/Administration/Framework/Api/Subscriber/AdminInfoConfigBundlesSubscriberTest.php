<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Api\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Api\Subscriber\AdminInfoConfigBundlesSubscriber;
use Shopware\Administration\Framework\App\ActiveAdminAppLoader;
use Shopware\Administration\Framework\Twig\ViteFileAccessorDecorator;
use Shopware\Core\Framework\Api\Event\AdminInfoConfigEvent;
use Shopware\Core\Test\Stub\Symfony\StubKernel;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Routing\RouterInterface;

/**
 * @internal
 */
#[CoversClass(AdminInfoConfigBundlesSubscriber::class)]
class AdminInfoConfigBundlesSubscriberTest extends TestCase
{
    #[TestDox('Subscribes to AdminInfoConfigEvent via the enrichBundles handler')]
    public function testSubscribesToAdminInfoConfigEvent(): void
    {
        $events = AdminInfoConfigBundlesSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(AdminInfoConfigEvent::class, $events);
        static::assertSame('enrichBundles', $events[AdminInfoConfigEvent::class]);
    }

    #[TestDox('enrichBundles() always sets the "bundles" key on the event (empty when nothing matches)')]
    public function testEnrichBundlesAlwaysSetsKey(): void
    {
        $loader = $this->createMock(ActiveAdminAppLoader::class);
        $loader->method('getActiveAdminApps')->willReturn([]);

        $subscriber = new AdminInfoConfigBundlesSubscriber(
            new StubKernel([]),
            $this->createMock(RouterInterface::class),
            $loader,
            new Filesystem(),
            $this->createMock(ViteFileAccessorDecorator::class),
        );

        $event = new AdminInfoConfigEvent([]);
        $subscriber->enrichBundles($event);

        $config = $event->getConfig();
        static::assertArrayHasKey('bundles', $config);
        static::assertSame([], $config['bundles']);
    }
}
