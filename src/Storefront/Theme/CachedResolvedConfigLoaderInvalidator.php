<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedResolvedConfigLoaderInvalidator implements EventSubscriberInterface
{
    private CacheInvalidator $logger;

    public function __construct(CacheInvalidator $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            ThemeConfigChangedEvent::class => 'invalidate',
            ThemeAssignedEvent::class => 'assigned',
            ThemeConfigResetEvent::class => 'reset',
        ];
    }

    public function invalidate(ThemeConfigChangedEvent $event): void
    {
        $tags = [CachedResolvedConfigLoader::buildName($event->getThemeId())];
        $keys = array_keys($event->getConfig());

        foreach ($keys as $key) {
            $tags[] = ThemeConfigValueAccessor::buildName($key);
        }

        $this->logger->invalidate($tags);
    }

    public function assigned(ThemeAssignedEvent $event): void
    {
        $this->logger->invalidate([CachedResolvedConfigLoader::buildName($event->getThemeId())]);
        $this->logger->invalidate([CachedDomainLoader::CACHE_KEY]);
    }

    public function reset(ThemeConfigResetEvent $event): void
    {
        $this->logger->invalidate([CachedResolvedConfigLoader::buildName($event->getThemeId())]);
    }
}
