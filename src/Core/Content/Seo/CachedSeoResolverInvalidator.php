<?php declare(strict_types=1);

namespace Shopware\Core\Content\Seo;

use Shopware\Core\Content\Seo\Event\SeoUrlUpdateEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedSeoResolverInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    public function __construct(CacheInvalidationLogger $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [SeoUrlUpdateEvent::class => 'invalidate'];
    }

    public function invalidate(SeoUrlUpdateEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }
        $urls = $event->getSeoUrls();

        $pathInfo = array_column($urls, 'pathInfo');

        $this->logger->log(array_map([CachedSeoResolver::class, 'buildName'], $pathInfo));
    }
}
