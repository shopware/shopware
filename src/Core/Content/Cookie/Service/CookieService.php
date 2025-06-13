<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
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
     * Returns a CookieGroupCollection based on the provided cookie groups and sales channel context.
     *
     * @param array<string|int, mixed> $cookieGroups
     */
    public function getCookieGroupCollection(array $cookieGroups, SalesChannelContext $salesChannelContext, bool $translate = true): CookieGroupCollection
    {
        $cookieGroups = $this->filterCookieGroups($salesChannelContext, $cookieGroups);

        if ($translate) {
            $cookieGroups = $this->translateCookieGroups($cookieGroups);
        }

        return $this->convertToCookieGroupCollection($cookieGroups);
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
            $cookieGroup = new CookieGroup(
                $group['isRequired'] ?? false,
                isset($group['entries']) && \is_array($group['entries'])
                    ? $this->convertCookieEntries($group['entries'])
                    : []
            );

            $this->setCookieProperties($cookieGroup, $group);
            $collection->add($cookieGroup);
        }

        return $collection;
    }

    /**
     * @param array<string|int, mixed> $cookieGroups
     *
     * @return array<string|int, mixed>
     */
    private function filterCookieGroups(SalesChannelContext $context, array $cookieGroups): array
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
    private function translateCookieGroups(array $cookieGroups): array
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
            $cookieEntry = new CookieEntry($entry['hidden'] ?? false);
            $this->setCookieProperties($cookieEntry, $entry);
            $convertedEntries[] = $cookieEntry;
        }

        return $convertedEntries;
    }

    /**
     * Sets the cookie properties from the given data array to the cookie object.
     *
     * @param array<string|int, mixed> $data
     */
    private function setCookieProperties(CookieEntry|CookieGroup $cookie, array $data): void
    {
        if (isset($data['snippet_name']) && $data['snippet_name'] !== '') {
            $cookie->snippetName = $data['snippet_name'];
        }
        if (isset($data['snippet_description']) && $data['snippet_description'] !== '') {
            $cookie->snippetDescription = $data['snippet_description'];
        }
        if (isset($data['cookie']) && $data['cookie'] !== '') {
            $cookie->cookie = $data['cookie'];
        }
        if (isset($data['value']) && $data['value'] !== '') {
            $cookie->value = $data['value'];
        }
        if (isset($data['expiration']) && $data['expiration'] !== '') {
            $cookie->expiration = $data['expiration'];
        }
    }
}
