<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Routing;

use Shopware\Core\Content\Seo\HreflangLoaderInterface;
use Shopware\Core\Content\Seo\HreflangLoaderParameter;
use Shopware\Core\Framework\App\ActiveAppsLoader;
use Shopware\Core\Framework\App\Exception\AppUrlChangeDetectedException;
use Shopware\Core\Framework\App\ShopId\ShopIdProvider;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Event\StorefrontRenderEvent;
use Shopware\Storefront\Theme\StorefrontPluginConfiguration\StorefrontPluginConfiguration;
use Shopware\Storefront\Theme\StorefrontPluginRegistryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * @internal
 */
#[Package('storefront')]
class TemplateDataSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly HreflangLoaderInterface $hreflangLoader,
        private readonly ShopIdProvider $shopIdProvider,
        private readonly StorefrontPluginRegistryInterface $themeRegistry,
        private readonly ActiveAppsLoader $activeAppsLoader,
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
        } catch (AppUrlChangeDetectedException) {
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

        if (\method_exists($this->themeRegistry, 'getByTechnicalName')) {
            /** @var StorefrontPluginConfiguration|null $themeConfig */
            $themeConfig = $this->themeRegistry->getByTechnicalName($theme);
        } else {
            $themeConfig = $this->themeRegistry->getConfigurations()->getByTechnicalName($theme);
        }

        if (!$themeConfig) {
            return;
        }

        $iconConfig = [];
        foreach ($themeConfig->getIconSets() as $pack => $path) {
            $iconConfig[$pack] = [
                'path' => $path,
                'namespace' => $theme,
            ];
        }

        $event->setParameter('themeIconConfig', $iconConfig);
    }
}
