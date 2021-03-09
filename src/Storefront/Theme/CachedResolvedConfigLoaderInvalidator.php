<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidationLogger;
use Shopware\Core\Framework\Feature;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CachedResolvedConfigLoaderInvalidator implements EventSubscriberInterface
{
    private CacheInvalidationLogger $logger;

    public function __construct(CacheInvalidationLogger $logger)
    {
        $this->logger = $logger;
    }

    public static function getSubscribedEvents()
    {
        return [
            ThemeConfigChangedEvent::class => 'invalidate',
        ];
    }

    public function invalidate(ThemeConfigChangedEvent $event): void
    {
        if (!Feature::isActive('FEATURE_NEXT_10514')) {
            return;
        }
        $tags = [CachedResolvedConfigLoader::buildName($event->getThemeId())];
        $keys = array_keys($event->getConfig());

        foreach ($keys as $key) {
            $tags[] = ThemeConfigValueAccessor::buildName($key);
        }

        $this->logger->log($tags);
    }
}
