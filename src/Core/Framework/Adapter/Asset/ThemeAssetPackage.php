<?php declare(strict_types=1);

namespace Shopware\Core\Framework\Adapter\Asset;

use Shopware\Core\Framework\Feature;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Storefront\Theme\ThemeCompiler;
use Symfony\Component\Asset\VersionStrategy\VersionStrategyInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ThemeAssetPackage extends FallbackUrlPackage
{
    private RequestStack $requestStack;

    public function __construct($baseUrls, VersionStrategyInterface $versionStrategy, RequestStack $requestStack)
    {
        parent::__construct($baseUrls, $versionStrategy);
        $this->requestStack = $requestStack;
    }

    public function getUrl(string $path)
    {
        if ($this->isAbsoluteUrl($path)) {
            return $path;
        }

        $url = $path;
        if ($url && $url[0] !== '/') {
            $url = '/' . $url;
        }

        if (str_starts_with($url, '/bundles') || str_starts_with($url, '/theme/')) {
            Feature::triggerDeprecated('FEATURE_NEXT_14699', '6.4.2.0', '6.5.0.0', 'Accessing "theme" asset with "/bundles" or "/themes" prefixed path will be removed with 6.5.0.0');

            $url = $this->getVersionStrategy()->applyVersion($url);

            if ($this->isAbsoluteUrl($url)) {
                return $url;
            }

            return $this->getBaseUrl($path) . $url;
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

        return '/theme/' . ThemeCompiler::getThemePrefix($salesChannelId, $themeId);
    }
}
