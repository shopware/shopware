<?php declare(strict_types=1);

namespace Shopware\Storefront\Controller;

use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Returns the cookie-configuration.html.twig template including all cookies returned by the "getCookieGroup"-method
 *
 * Cookies are returned within groups, groups require the "group" attribute
 * A group is structured as described above the "getCookieGroup"-method
 *
 * @internal
 * Do not use direct or indirect repository calls in a controller. Always use a store-api route to get or put data
 */
#[Route(defaults: ['_routeScope' => ['storefront']])]
#[Package('storefront')]
class CookieController extends StorefrontController
{
    /**
     * @internal
     *
     * @param EntityRepository<SalesChannelAnalyticsCollection> $salesChannelAnalyticsRepository
     */
    public function __construct(
        private readonly CookieProviderInterface $cookieProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $salesChannelAnalyticsRepository
    ) {
    }

    #[Route(path: '/cookie/offcanvas', name: 'frontend.cookie.offcanvas', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function offcanvas(SalesChannelContext $context): Response
    {
        $response = $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-configuration.html.twig', [
            'cookieGroups' => $this->getCookieGroups($context),
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    #[Route(path: '/cookie/permission', name: 'frontend.cookie.permission', options: ['seo' => false], defaults: ['XmlHttpRequest' => true], methods: ['GET'])]
    public function permission(SalesChannelContext $context): Response
    {
        $response = $this->renderStorefront('@Storefront/storefront/layout/cookie/cookie-permission.html.twig', [
            'cookieGroups' => $this->getCookieGroups($context),
        ]);
        $response->headers->set('x-robots-tag', 'noindex,follow');

        return $response;
    }

    /**
     * @return array<mixed>
     */
    private function getCookieGroups(SalesChannelContext $context): array
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();
        $cookieGroups = $this->filterGoogleAnalyticsCookie($context, $cookieGroups);
        $cookieGroups = $this->filterWishlistCookie($context->getSalesChannelId(), $cookieGroups);
        $cookieGroups = $this->filterGoogleReCaptchaCookie($context->getSalesChannelId(), $cookieGroups);

        return $cookieGroups;
    }

    /**
     * @param array<string|int, mixed> $cookieGroups
     *
     * @return array<string|int, mixed>
     */
    private function filterGoogleAnalyticsCookie(SalesChannelContext $context, array $cookieGroups): array
    {
        $salesChannel = $context->getSalesChannel();

        if ($salesChannel->getAnalytics() === null && $salesChannel->getAnalyticsId() !== null) {
            $criteria = new Criteria([$salesChannel->getAnalyticsId()]);
            $criteria->setTitle('cookie-controller::load-analytics');

            $salesChannel->setAnalytics(
                $this->salesChannelAnalyticsRepository->search($criteria, $context->getContext())->getEntities()->first()
            );
        }

        if ($salesChannel->getAnalytics()?->isActive() === true) {
            return $cookieGroups;
        }

        $filteredGroups = [];
        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup['snippet_name'] === 'cookie.groupStatistical') {
                $cookieGroup = $this->filterCookieGroup('cookie.groupStatisticalGoogleAnalytics', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            } elseif ($cookieGroup['snippet_name'] === 'cookie.groupMarketing') {
                $cookieGroup = $this->filterCookieGroup('cookie.groupMarketingAdConsent', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            $filteredGroups[] = $cookieGroup;
        }

        return $filteredGroups;
    }

    /**
     * @param array<string|int, mixed> $cookieGroups
     *
     * @return array<string|int, mixed>
     */
    private function filterWishlistCookie(string $salesChannelId, array $cookieGroups): array
    {
        if ($this->systemConfigService->getBool('core.cart.wishlistEnabled', $salesChannelId)) {
            return $cookieGroups;
        }

        $filteredGroups = [];
        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup['snippet_name'] === 'cookie.groupComfortFeatures') {
                $cookieGroup = $this->filterCookieGroup('cookie.groupComfortFeaturesWishlist', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            $filteredGroups[] = $cookieGroup;
        }

        return $filteredGroups;
    }

    /**
     * @param array<string|int, mixed> $cookieGroups
     *
     * @return array<string|int, mixed>
     */
    private function filterGoogleReCaptchaCookie(string $salesChannelId, array $cookieGroups): array
    {
        $googleRecaptchaActive = $this->systemConfigService->getBool(
            'core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV2::CAPTCHA_NAME . '.isActive',
            $salesChannelId
        ) || $this->systemConfigService->getBool(
            'core.basicInformation.activeCaptchasV2.' . GoogleReCaptchaV3::CAPTCHA_NAME . '.isActive',
            $salesChannelId
        );

        if ($googleRecaptchaActive) {
            return $cookieGroups;
        }

        $filteredGroups = [];
        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup['snippet_name'] === 'cookie.groupRequired') {
                $cookieGroup = $this->filterCookieGroup('cookie.groupRequiredCaptcha', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            $filteredGroups[] = $cookieGroup;
        }

        return $filteredGroups;
    }

    /**
     * @param array<mixed> $cookieGroup
     *
     * @return ?array<mixed>
     */
    private function filterCookieGroup(string $cookieSnippetName, array $cookieGroup): ?array
    {
        $cookieGroup['entries'] = array_filter($cookieGroup['entries'], fn ($item) => $item['snippet_name'] !== $cookieSnippetName);
        if (\count($cookieGroup['entries']) === 0) {
            return null;
        }

        return $cookieGroup;
    }
}
