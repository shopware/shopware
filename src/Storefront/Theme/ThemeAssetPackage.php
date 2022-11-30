<?php declare(strict_types=1);

namespace Shopware\Storefront\Theme;

use Shopware\Core\Framework\Adapter\Asset\FallbackUrlPackage;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ThemeAssetPackage extends FallbackUrlPackage
{
    private RequestStack $requestStack;

    private AbstractThemePathBuilder $themePathBuilder;

    /**
     * @internal
     */
    public function __construct(
        $baseUrls,
        VersionStrategyInterface $versionStrategy,
        RequestStack $requestStack,
        AbstractThemePathBuilder $themePathBuilder
    ) {
        parent::__construct($baseUrls, $versionStrategy);
        $this->requestStack = $requestStack;
        $this->themePathBuilder = $themePathBuilder;
    }

    public function getUrl(string $path): string
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        $url = $path;
        if ($url && $url[0] !== '/') {
            $url = '/' . $url;
        }

        $url = $this->getVersionStrategy()->applyVersion($this->appendThemePath() . $url);

        if ($this->isAbsoluteUrl($url)) {
            return $url;
        }

        return $this->getBaseUrl($path) . $url;
    }

    private function appendThemePath(): string
    {
        $currentRequest = $this->requestStack->getMainRequest();

        if ($currentRequest === null) {
            return '';
        }

        $salesChannelId = $currentRequest->attributes->get(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID);
        $themeId = $currentRequest->attributes->get(SalesChannelRequest::ATTRIBUTE_THEME_ID);

        if ($themeId === null || $salesChannelId === null) {
            return '';
        }

        return '/theme/' . $this->themePathBuilder->assemblePath($salesChannelId, $themeId);
    }
}
