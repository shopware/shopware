<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\HttpFoundation\RequestStack;

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
        private RequestStack $requestStack
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

        if ($themeName === null) {
            return [];
        }

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
    }
}
