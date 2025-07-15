<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Store\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEvents;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEvents;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Subscriber\ExtensionChangedSubscriber;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ExtensionChangedSubscriber::class)]
class ExtensionChangedSubscriberTest extends TestCase
{
    private CacheInterface&MockObject $cache;

    private ExtensionChangedSubscriber $subscriber;

    protected function setUp(): void
    {
        $this->cache = $this->createMock(CacheInterface::class);
        $this->subscriber = new ExtensionChangedSubscriber($this->cache);
    }

    public function testItSubscribesToPluginAndAppWrittenEvents(): void
    {
        $expected = [
            PluginEvents::PLUGIN_WRITTEN_EVENT => 'onExtensionChanged',
            AppEvents::APP_WRITTEN_EVENT => 'onExtensionChanged',
        ];

        static::assertSame($expected, ExtensionChangedSubscriber::getSubscribedEvents());
    }

    public function testItDeletesExtensionListCacheOnExtensionChanged(): void
    {
        $this->cache
            ->expects($this->once())
            ->method('delete')
            ->with(StoreClient::EXTENSION_LIST_CACHE);

        $this->subscriber->onExtensionChanged();
    }
}
