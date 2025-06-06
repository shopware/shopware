<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\SalesChannel;

use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Content\Cookie\Struct\CookieStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Shopware\Storefront\Framework\Cookie\CookieProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['store-api']])]
#[Package('framework')]
class CookieRoute extends AbstractCookieRoute
{
    /**
     * @param EntityRepository<SalesChannelAnalyticsCollection> $salesChannelAnalyticsRepository
     *
     * @internal
     */
    public function __construct(
        private readonly CookieProviderInterface $cookieProvider,
        private readonly SystemConfigService $systemConfigService,
        private readonly EntityRepository $salesChannelAnalyticsRepository
    ) {
    }

    public function getDecorated(): AbstractCookieRoute
    {
        throw new DecorationPatternException(self::class);
    }

    #[Route(path: '/store-api/cookie-groups', name: 'store-api.cookie.groups', methods: ['GET'])]
    public function getCookieGroups(Request $request, SalesChannelContext $salesChannelContext): CookieRouteResponse
    {
        $cookieGroups = $this->cookieProvider->getCookieGroups();
        if (empty($cookieGroups)) {
            return new CookieRouteResponse(new CookieGroupCollection());
        }

        $cookieGroups = $this->filterGoogleAnalyticsCookie($salesChannelContext, $cookieGroups);
        $cookieGroups = $this->filterWishlistCookie($salesChannelContext->getSalesChannelId(), $cookieGroups);
        $cookieGroups = $this->filterGoogleReCaptchaCookie($salesChannelContext->getSalesChannelId(), $cookieGroups);
        $cookieGroups = $this->convertToCookieGroupCollection($cookieGroups);

        return new CookieRouteResponse($cookieGroups);
    }

    /**
     * Converts an array of cookie groups to a CookieGroupCollection.
     *
     * @param array<string|int, mixed> $cookieGroups
     */
    private function convertToCookieGroupCollection(array $cookieGroups): CookieGroupCollection
    {
        $collection = new CookieGroupCollection();
        foreach ($cookieGroups as $group) {
            $cookieStruct = new CookieStruct();
            $cookieStruct = $this->transformValuesToStruct($cookieStruct, $group);

            $cookieGroup = new CookieGroup(
                $group['isRequired'] ?? false,
                isset($group['entries']) && \is_array($group['entries'])
                    ? $this->convertCookieEntries($group['entries'])
                    : []
            );
            $cookieGroup->assign($cookieStruct->jsonSerialize());

            $collection->add($cookieGroup);
        }

        return $collection;
    }

    /**
     * Transforms the provided data into a CookieGroup or CookieEntry struct.
     *
     * @param array<string|int, mixed> $data
     */
    private function transformValuesToStruct(
        CookieStruct $cookieStruct,
        array $data
    ): CookieStruct {
        if (!empty($data['snippet_name'])) {
            $cookieStruct->setSnippetName($data['snippet_name']);
        }
        if (!empty($data['snippet_description'])) {
            $cookieStruct->setSnippetDescription($data['snippet_description']);
        }
        if (!empty($data['cookie'])) {
            $cookieStruct->setCookie($data['cookie']);
        }
        if (!empty($data['value'])) {
            $cookieStruct->setValue($data['value']);
        }
        if (!empty($data['expiration'])) {
            $cookieStruct->setExpiration($data['expiration']);
        }

        return $cookieStruct;
    }

    /**
     * Converts an array of cookie entries to an array of CookieEntry objects.
     *
     * @param array<string|int, mixed> $entries
     *
     * @return list<CookieEntry>
     */
    private function convertCookieEntries(array $entries): array
    {
        $convertedEntries = [];
        foreach ($entries as $entry) {
            $cookieStruct = new CookieStruct();
            $cookieStruct = $this->transformValuesToStruct($cookieStruct, $entry);

            $cookieEntry = new CookieEntry($entry['hidden'] ?? false);
            $cookieEntry->assign($cookieStruct->jsonSerialize());

            $convertedEntries[] = $cookieEntry;
        }

        return $convertedEntries;
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
     * @param array<string|int, mixed> $cookieGroup
     *
     * @return ?array<string|int, mixed>
     */
    private function filterCookieGroup(string $cookieSnippetName, array $cookieGroup): ?array
    {
        $cookieGroup['entries'] = array_filter($cookieGroup['entries'], fn ($item) => $item['snippet_name'] !== $cookieSnippetName);
        if (\count($cookieGroup['entries']) === 0) {
            return null;
        }

        return $cookieGroup;
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
}
