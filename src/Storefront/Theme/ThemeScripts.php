<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
readonly class ThemeScripts
{
    /**
     * @internal
     */
    public function __construct(
        private RequestStack $requestStack,
        private ThemeRuntimeConfigService $themeRuntimeConfigService,
    ) {
    }

    /**
     * @return array<string>
     */
    public function getThemeScripts(): array
    {
        $runtimeConfig = $this->getThemeRuntimeConfig();

        if ($runtimeConfig?->scriptFiles === null) {
            return [];
        }

        return $runtimeConfig->scriptFiles;
    }

    /**
     * Returns the pre-built component import map stored in the runtime config, or null when
     * no import map has been compiled yet (first-run / test environment without a build).
     *
     * Paths inside the map are theme-relative (e.g. 'js/components/Sw/Filter/Sorting.js').
     * TemplateConfigAccessor converts them to full URLs at request time.
     *
     * @return array{imports: array<string, string>, scopes?: array<string, array<string, string>>}|null
     */
    public function getComponentImportMap(): ?array
    {
        return $this->getThemeRuntimeConfig()?->componentImportMap;
    }

    private function getThemeRuntimeConfig(): ?ThemeRuntimeConfig
    {
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return null;
        }

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        if ($themeId === null) {
            return null;
        }

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$salesChannelContext instanceof SalesChannelContext) {
            return null;
        }

        return $this->themeRuntimeConfigService->getResolvedRuntimeConfig($themeId);
    }
}
