<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Framework\Store\Subscriber;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\App\AppEvents;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\PluginEvents;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Shopware\Core\Framework\Store\Subscriber\ExtensionChangedSubscriber;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(ExtensionChangedSubscriber::class)]
class ExtensionChangedSubscriberTest extends TestCase
{
    private ArrayAdapter $cache;

    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->cache = new ArrayAdapter();
        $this->dispatcher = new EventDispatcher();

        $subscriber = new ExtensionChangedSubscriber($this->cache);
        $this->dispatcher->addSubscriber($subscriber);

        $this->cache->get(StoreClient::EXTENSION_LIST_CACHE, fn () => 'test-value');
    }

    public function testPluginWrittenEventClearsCache(): void
    {
        $item = $this->cache->getItem(StoreClient::EXTENSION_LIST_CACHE);
        static::assertTrue($item->isHit());

        $this->dispatcher->dispatch(new \stdClass(), PluginEvents::PLUGIN_WRITTEN_EVENT);

        $item = $this->cache->getItem(StoreClient::EXTENSION_LIST_CACHE);
        static::assertFalse($item->isHit());
    }

    public function testAppWrittenEventClearsCache(): void
    {
        $this->cache->get(StoreClient::EXTENSION_LIST_CACHE, fn () => 'test-value');

        $item = $this->cache->getItem(StoreClient::EXTENSION_LIST_CACHE);
        static::assertTrue($item->isHit());

        $this->dispatcher->dispatch(new \stdClass(), AppEvents::APP_WRITTEN_EVENT);

        $item = $this->cache->getItem(StoreClient::EXTENSION_LIST_CACHE);
        static::assertFalse($item->isHit());
    }
}
