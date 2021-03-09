<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\Feature;
use Shopware\Core\System\SystemConfig\Event\SystemConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedSystemConfigLoaderInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    public function __construct(CacheInvalidationLogger $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [SystemConfigChangedEvent::class => 'invalidate'];
    }

    public function invalidate(SystemConfigChangedEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }

        $this->logger->log([
            SystemConfigService::buildName($event->getKey()),
            CachedSystemConfigLoader::CACHE_TAG,
        ]);
    }
}
