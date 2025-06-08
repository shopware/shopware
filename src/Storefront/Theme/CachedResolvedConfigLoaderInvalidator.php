<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Cache\CacheInvalidator;
use Shopware\Core\Framework\Adapter\Translation\Translator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Storefront\Framework\Routing\CachedDomainLoader;
use Shopware\Storefront\Theme\Event\ThemeAssignedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigChangedEvent;
use Shopware\Storefront\Theme\Event\ThemeConfigResetEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class CachedResolvedConfigLoaderInvalidator implements EventSubscriberInterface
{
    /**
     * @internal
     */
    public function __construct(
        private readonly CacheInvalidator $cacheInvalidator,
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
        $tags = [CachedResolvedConfigLoader::buildName($event->getThemeId())];

        $this->cacheInvalidator->invalidate($tags);
    }

    public function assigned(ThemeAssignedEvent $event): void
    {
        $salesChannelId = $event->getSalesChannelId();

        $this->cacheInvalidator->invalidate([
            CachedResolvedConfigLoader::buildName($event->getThemeId()),
            CachedDomainLoader::CACHE_KEY,
            Translator::tag($salesChannelId),
        ]);
    }

    public function reset(ThemeConfigResetEvent $event): void
    {
        $this->cacheInvalidator->invalidate([CachedResolvedConfigLoader::buildName($event->getThemeId())]);
    }
}
