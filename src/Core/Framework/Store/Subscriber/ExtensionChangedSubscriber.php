<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Store\Subscriber;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Store\Services\StoreClient;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * @internal
 */
#[Package('checkout')]
readonly class ExtensionChangedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private CacheInterface $cache
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            'plugin.written' => 'onExtensionChanged',
            'app.written' => 'onExtensionChanged',
        ];
    }

    public function onExtensionChanged(): void
    {
        $this->cache->delete(StoreClient::EXTENSION_LIST_CACHE);
    }
}
