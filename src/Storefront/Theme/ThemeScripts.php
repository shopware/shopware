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
        $request = $this->requestStack->getMainRequest();

        if ($request === null) {
            return [];
        }

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        if ($themeId === null) {
            return [];
        }

        $salesChannelContext = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT);
        if (!$salesChannelContext instanceof SalesChannelContext) {
            return [];
        }

        $runtimeConfig = $this->themeRuntimeConfigService->getResolvedRuntimeConfig($themeId);

        if ($runtimeConfig === null) {
            return [];
        }
        \assert($runtimeConfig->scriptFiles !== null);

        return $runtimeConfig->scriptFiles;
    }
}
