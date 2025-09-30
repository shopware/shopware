<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Exception\ShopIdChangeSuggestedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Theme\ThemeRuntimeConfigService;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('framework')]
class TemplateDataSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly HreflangLoaderInterface $hreflangLoader,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly ActiveAppsLoader $activeAppsLoader,
        private readonly ThemeRuntimeConfigService $runtimeConfigService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StorefrontRenderEvent::class => [
                ['addHreflang'],
                ['addShopIdParameter'],
                ['addIconSetConfig'],
            ],
        ];
    }

    public function addHreflang(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();
        $route = $request->attributes->get('_route');

        if ($route === null) {
            return;
        }

        $routeParams = $request->attributes->get('_route_params', []);
        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        $parameter = new HreflangLoaderParameter($route, $routeParams, $salesChannelContext);
        $event->setParameter('hrefLang', $this->hreflangLoader->load($parameter));
    }

    public function addShopIdParameter(StorefrontRenderEvent $event): void
    {
        if (!$this->activeAppsLoader->getActiveApps()) {
            return;
        }

        try {
            $shopId = $this->shopIdProvider->getShopId();
        } catch (ShopIdChangeSuggestedException) {
            return;
        }

        $event->setParameter('appShopId', $shopId);
    }

    public function addIconSetConfig(StorefrontRenderEvent $event): void
    {
        $request = $event->getRequest();

        // get name if theme is not inherited
        $theme = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_NAME);
        if (!$theme) {
            // get theme name from base theme because for inherited themes the name is always null
            $theme = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME);
        }

        if (!$theme) {
            return;
        }

        $runtimeConfig = $this->runtimeConfigService->getRuntimeConfigByName($theme);
        if (!$runtimeConfig) {
            return;
        }

        $event->setParameter('themeIconConfig', $runtimeConfig->iconSets);
    }
}
