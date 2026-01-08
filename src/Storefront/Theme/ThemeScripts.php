<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequestAttribute;
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
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return [];
        }

        $themeId = $request->attributes->get(SalesChannelRequestAttribute::THEME_ID->value);

        if ($themeId === null) {
            return [];
        }

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$salesChannelContext instanceof SalesChannelContext) {
            return [];
        }

        $runtimeConfig = $this->themeRuntimeConfigService->getResolvedRuntimeConfig($themeId);

        if ($runtimeConfig?->scriptFiles === null) {
            return [];
        }

        return $runtimeConfig->scriptFiles;
    }
}
