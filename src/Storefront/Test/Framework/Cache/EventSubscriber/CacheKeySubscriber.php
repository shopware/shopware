<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CacheKeySubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['Shopware\Storefront\Framework\Cache\Event\HttpCacheGenerateKeyEvent' => ['changeCacheKey', 0]];
    }

    public function changeCacheKey($event)
    {
        $event->setHash(hash('sha256', 'a'));
    }
}
