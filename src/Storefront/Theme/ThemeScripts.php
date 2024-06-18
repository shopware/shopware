<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * @internal
 */
#[Package('storefront')]
readonly class ThemeScripts
{
    /**
     * @internal
     */
    public function __construct(
        private StorefrontPluginRegistryInterface $pluginRegistry,
        private ThemeFileResolver $themeFileResolver,
        private RequestStack $requestStack,
        private AbstractThemePathBuilder $themePathBuilder,
        private CacheInterface $cache
    ) {
    }

    /**
     * @return array<int, string>
     */
    public function getThemeScripts(): array
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return [];
        }

        $themeName = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_NAME, SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME)
            ?? $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_BASE_NAME);

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        if ($themeName === null || $themeId === null) {
            return [];
        }

        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $path = $this->themePathBuilder->assemblePath($salesChannelId, $themeId);

        return $this->cache->get('theme_scripts_' . $path, function (ItemInterface $item) use ($themeName) {
            $themeConfig = $this->pluginRegistry->getConfigurations()->getByTechnicalName($themeName);

            if ($themeConfig === null) {
                return [];
            }

            $resolvedFiles = $this->themeFileResolver->resolveFiles(
                $themeConfig,
                $this->pluginRegistry->getConfigurations(),
                false
            );

            return $resolvedFiles[ThemeFileResolver::SCRIPT_FILES]->getPublicPaths('js');
        });
    }
}
