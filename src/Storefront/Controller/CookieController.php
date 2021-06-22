<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Annotation\Since;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Returns the cookie-configuration.html.twig template including all cookies returned by the "getCookieGroup"-method
 *
 * Cookies are returned within groups, groups require the "group" attribute
 * A group is structured as described above the "getCookieGroup"-method
 *
 * @RouteScope(scopes={"storefront"})
 */
class CookieController extends StorefrontController
{
    /**
     * @var CookieProviderInterface
     */
    private $cookieProvider;

    /**
     * @var SystemConfigService
     */
    private $systemConfigService;

    public function __construct(CookieProviderInterface $cookieProvider, SystemConfigService $systemConfigService)
    {
        $this->cookieProvider = $cookieProvider;
        $this->systemConfigService = $systemConfigService;
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/cookie/offcanvas", name="frontend.cookie.offcanvas", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function offcanvas(SalesChannelContext $context): Response
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();
        $cookieGroups = $this->filterGoogleAnalyticsCookie($context, $cookieGroups);

        $cookieGroups = $this->filterComfortFeaturesCookie($context->getSalesChannelId(), $cookieGroups);

        $cookieGroups = $this->filterGoogleReCaptchaCookie($context->getSalesChannelId(), $cookieGroups);

        return $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', ['cookieGroups' => $cookieGroups]);
    }

    /**
     * @Since("6.1.0.0")
     * @Route("/cookie/permission", name="frontend.cookie.permission", options={"seo"="false"}, methods={"GET"}, defaults={"XmlHttpRequest"=true})
     */
    public function permission(SalesChannelContext $context): Response
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();
        $cookieGroups = $this->filterGoogleAnalyticsCookie($context, $cookieGroups);

        $cookieGroups = $this->filterComfortFeaturesCookie($context->getSalesChannelId(), $cookieGroups);

        $cookieGroups = $this->filterGoogleReCaptchaCookie($context->getSalesChannelId(), $cookieGroups);

        return $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', ['cookieGroups' => $cookieGroups]);
    }

    private function filterGoogleAnalyticsCookie(SalesChannelContext $context, array $cookieGroups): array
    {
        if ($context->getSalesChannel()->getAnalytics() && $context->getSalesChannel()->getAnalytics()->isActive()) {
            return $cookieGroups;
        }

        $filteredGroups = [];

        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup['snippet_name'] === 'cookie.groupStatistical') {
                $cookieGroup['entries'] = array_filter($cookieGroup['entries'], function ($item) {
                    return $item['snippet_name'] !== 'cookie.groupStatisticalGoogleAnalytics';
                });
                // Only add statistics cookie group if it has entries
                if (\count($cookieGroup['entries']) > 0) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }
            $filteredGroups[] = $cookieGroup;
        }

        return $filteredGroups;
    }

    private function filterComfortFeaturesCookie(string $salesChannelId, array $cookieGroups): array
    {
        foreach ($cookieGroups as $groupIndex => $cookieGroup) {
            if ($cookieGroup['snippet_name'] !== 'cookie.groupComfortFeatures') {
                continue;
            }

            foreach ($cookieGroup['entries'] as $entryIndex => $entry) {
                if ($entry['snippet_name'] !== 'cookie.groupComfortFeaturesWishlist') {
                    continue;
                }

                if (!$this->systemConfigService->get('core.cart.wishlistEnabled', $salesChannelId)) {
                    unset($cookieGroups[$groupIndex]['entries'][$entryIndex]);
                }
            }

            if (\count($cookieGroups[$groupIndex]['entries']) === 0) {
                unset($cookieGroups[$groupIndex]);
            }
        }

        return $cookieGroups;
    }

    private function filterGoogleReCaptchaCookie(string $salesChannelId, array $cookieGroups): array
    {
        foreach ($cookieGroups as $groupIndex => $cookieGroup) {
            if ($cookieGroup['snippet_name'] !== 'cookie.groupRequired') {
                continue;
            }

            foreach ($cookieGroup['entries'] as $entryIndex => $entry) {
                if ($entry['snippet_name'] !== 'cookie.groupRequiredCaptcha') {
                    continue;
                }

                $activeGreCaptchaV2 = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV2::CAPTCHA_NAME . '.isActive', $salesChannelId) ?? false;
                $activeGreCaptchaV3 = $this->systemConfigService->get('core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV3::CAPTCHA_NAME . '.isActive', $salesChannelId) ?? false;

                if (!$activeGreCaptchaV2 && !$activeGreCaptchaV3) {
                    unset($cookieGroups[$groupIndex]['entries'][$entryIndex]);
                }
            }

            if (\count($cookieGroups[$groupIndex]['entries']) === 0) {
                unset($cookieGroups[$groupIndex]);
            }
        }

        return $cookieGroups;
    }
}
