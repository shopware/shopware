<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('storefront')]
class CachedResolvedConfigLoaderInvalidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
        private readonly bool $fineGrainedCache
    ) {
    }

    /**
     * @return array<string, string|array{0: string, 1: int}|list<array{0: string, 1?: int}>>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ThemeConfigChangedEvent::class => 'invalidate',
            ThemeAssignedEvent::class => 'assigned',
            ThemeConfigResetEvent::class => 'reset',
        ];
    }

    public function invalidate(ThemeConfigChangedEvent $event): void
    {
        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->invalidate(['shopware.theme']);

            return;
        }

        $tags = [CachedResolvedConfigLoader::buildName($event->getThemeId())];

        if (!$this->fineGrainedCache) {
            $this->cacheInvalidator->invalidate(['shopware.theme']);

            return;
        }

        $keys = array_keys($event->getConfig());

        foreach ($keys as $key) {
            $tags[] = ThemeConfigValueAccessor::buildName($key);
        }

        $this->cacheInvalidator->invalidate($tags);
    }

    public function assigned(ThemeAssignedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelId();

        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->invalidate(['shopware.theme']);

            return;
        }

        $this->cacheInvalidator->invalidate([
            CachedResolvedConfigLoader::buildName($event->getThemeId()),
            CachedDomainLoader::CACHE_KEY,
            'translation.catalog.' . $salesChannelId,
        ]);
    }

    public function reset(ThemeConfigResetEvent $event): void
    {
        if (Feature::isActive('cache_rework')) {
            $this->cacheInvalidator->invalidate(['shopware.theme']);

            return;
        }

        $this->cacheInvalidator->invalidate([CachedResolvedConfigLoader::buildName($event->getThemeId())]);
    }
}
