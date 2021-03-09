<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\SalesChannel\Search;

use Shopware\Core\Content\Product\Events\ProductIndexerEvent;
use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\Feature;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedProductSearchRouteInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    public function __construct(
        CacheInvalidationLogger $logger
    ) {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [ProductIndexerEvent::class => 'invalidate'];
    }

    public function invalidate(): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }
        $this->logger->log(['product-search-route']);
    }
}
