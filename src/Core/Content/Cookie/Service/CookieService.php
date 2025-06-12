<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Content\Cookie\Struct\CookieStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV2;
use Shopware\Storefront\Framework\Captcha\GoogleReCaptchaV3;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[Package('framework')]
readonly class CookieService
{
    /**
     * @param EntityRepository<SalesChannelAnalyticsCollection> $salesChannelAnalyticsRepository
     *
     * @internal
     */
    public function __construct(
        private SystemConfigService $systemConfigService,
        private EntityRepository $salesChannelAnalyticsRepository,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Converts an array of cookie groups to a CookieGroupCollection.
     *
     * @param array<string|int, mixed> $cookieGroups
     */
    public function convertToCookieGroupCollection(array $cookieGroups): CookieGroupCollection
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
     * @param array<string|int, mixed> $cookieGroups
     *
     * @return array<string|int, mixed>
     */
    public function filterCookieGroups(SalesChannelContext $context, array $cookieGroups): array
    {
        $cookieGroups = $this->filterGoogleAnalyticsCookie($context, $cookieGroups);
        $cookieGroups = $this->filterWishlistCookie($context->getSalesChannelId(), $cookieGroups);

        return $this->filterGoogleReCaptchaCookie($context->getSalesChannelId(), $cookieGroups);
    }

    /**
     * Translates snippet names and descriptions in cookie groups.
     *
     * @param array<string|int, mixed> $cookieGroups
     *
     * @return array<string|int, mixed>
     */
    public function translateCookieGroups(array $cookieGroups, SalesChannelContext $context): array
    {
        $translatedGroups = [];

        foreach ($cookieGroups as $group) {
            $translatedGroup = $group;

            // Translate group snippet_name and snippet_description
            if (!empty($group['snippet_name'])) {
                $translatedGroup['snippet_name'] = $this->translator->trans($group['snippet_name']);
            }
            if (!empty($group['snippet_description'])) {
                $translatedGroup['snippet_description'] = $this->translator->trans($group['snippet_description']);
            }

            // Translate entries
            if (isset($group['entries']) && \is_array($group['entries'])) {
                $translatedEntries = [];
                foreach ($group['entries'] as $entry) {
                    $translatedEntry = $entry;
                    if (!empty($entry['snippet_name'])) {
                        $translatedEntry['snippet_name'] = $this->translator->trans($entry['snippet_name']);
                    }
                    if (!empty($entry['snippet_description'])) {
                        $translatedEntry['snippet_description'] = $this->translator->trans($entry['snippet_description']);
                    }
                    $translatedEntries[] = $translatedEntry;
                }
                $translatedGroup['entries'] = $translatedEntries;
            }

            $translatedGroups[] = $translatedGroup;
        }

        return $translatedGroups;
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
            $cookieStruct->snippetName = $data['snippet_name'];
        }
        if (!empty($data['snippet_description'])) {
            $cookieStruct->snippetDescription = $data['snippet_description'];
        }
        if (!empty($data['cookie'])) {
            $cookieStruct->cookie = $data['cookie'];
        }
        if (!empty($data['value'])) {
            $cookieStruct->value = $data['value'];
        }
        if (!empty($data['expiration'])) {
            $cookieStruct->expiration = $data['expiration'];
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
}
