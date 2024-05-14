<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('storefront')]
readonly class ThemeScripts
{
    public const SCRIPT_FILES_CONFIG_KEY = 'storefront.theme.scriptFiles';

    /**
     * @internal
     */
    public function __construct(
        private AbstractThemePathBuilder $themePathBuilder,
        private SystemConfigService $systemConfig,
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

        $themeId = $request->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);
        if ($themeId === null) {
            return [];
        }

        $salesChannelId = $request->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        if ($salesChannelId === null) {
            return [];
        }

        $themePrefix = $this->themePathBuilder->assemblePath($salesChannelId, $themeId);

        /** @var array<int, string> $scripts */
        $scripts = $this->systemConfig->get(ThemeScripts::SCRIPT_FILES_CONFIG_KEY . '.' . $themePrefix) ?? [];

        return $scripts;
    }
}
