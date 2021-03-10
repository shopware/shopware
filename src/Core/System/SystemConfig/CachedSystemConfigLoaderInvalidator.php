<?php declare(strict_types=1);

namespace Shopware\Core\System\SystemConfig;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\Plugin\Event\PluginPostActivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostDeactivateEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostInstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUninstallEvent;
use Shopware\Core\Framework\Plugin\Event\PluginPostUpdateEvent;
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
        return [
            SystemConfigChangedEvent::class => 'invalidate',
            PluginPostInstallEvent::class => 'invalidateAll',
            PluginPostActivateEvent::class => 'invalidateAll',
            PluginPostUpdateEvent::class => 'invalidateAll',
            PluginPostDeactivateEvent::class => 'invalidateAll',
            PluginPostUninstallEvent::class => 'invalidateAll',
        ];
    }

    public function invalidateAll(): void
    {
        $this->logger->log([
            CachedSystemConfigLoader::CACHE_TAG,
        ]);
    }

    public function invalidate(SystemConfigChangedEvent $event): void
    {
        $this->logger->log([
            SystemConfigService::buildName($event->getKey()),
            CachedSystemConfigLoader::CACHE_TAG,
        ]);
    }
}
