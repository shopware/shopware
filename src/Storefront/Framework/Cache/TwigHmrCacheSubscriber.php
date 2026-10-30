<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cache;

use Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheKeyEvent;
use Shopware\Core\Framework\Log\Package;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * The Twig hot reload of the Storefront watcher renders the page again after a template changed.
 * A cached response would still show the old template, so its requests bypass the HTTP cache in debug mode.
 *
 * @internal
 */
#[Package('framework')]
class TwigHmrCacheSubscriber implements EventSubscriberInterface
{
    final public const HEADER = 'sw-twig-hmr';

    public function __construct(private readonly bool $debug)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            HttpCacheKeyEvent::class => 'bypassCache',
        ];
    }

    public function bypassCache(HttpCacheKeyEvent $event): void
    {
        if (!$this->debug || $event->request->headers->get(self::HEADER) !== '1') {
            return;
        }

        $event->isCacheable = false;
    }
}
