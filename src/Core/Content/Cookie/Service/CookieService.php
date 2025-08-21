<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
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
     */
    public function __construct(
        private SystemConfigService $systemConfigService,
        private EntityRepository $salesChannelAnalyticsRepository,
        private TranslatorInterface $translator
    ) {
    }

    //    /**
    //     * Returns a CookieGroupCollection based on the provided cookie groups and sales channel context.
    //     *
    //     * @param array<string|int, mixed> $cookieGroups
    //     */
    //    public function getCookieGroupCollection(array $cookieGroups, ?SalesChannelContext $salesChannelContext, bool $translate = true): CookieGroupCollection
    //    {
    //        $cookieGroups = $this->normalizeCookieGroups($cookieGroups);
    //        if ($salesChannelContext !== null) {
    //            $cookieGroups = $this->filterCookieGroups($salesChannelContext, $cookieGroups);
    //        }
    //
    //        if ($translate) {
    //            $cookieGroups = $this->translateCookieGroups($cookieGroups);
    //        }
    //
    //        return $this->convertToCookieGroupCollection($cookieGroups);
    //    }
    //
    //    /**
    //     * Converts an array of cookie groups to a CookieGroupCollection.
    //     *
    //     * @param array<string|int, CookieGroup> $cookieGroups
    //     */
    //    private function convertToCookieGroupCollection(array $cookieGroups): CookieGroupCollection
    //    {
    //        $collection = new CookieGroupCollection();
    //        foreach ($cookieGroups as $group) {
    //            $collection->add($group);
    //        }
    //
    //        return $collection;
    //    }
    //
    //    /**
    //     * @param array<string|int, mixed|CookieGroup> $cookieGroups // legacy arrays OR CookieGroup[]
    //     *
    //     * @return array<string|int, mixed|CookieGroup>
    //     */
    //    private function normalizeCookieGroups(array $cookieGroups): array
    //    {
    //        // NEW typed shape (array of CookieGroup instances)
    //        if (!empty($cookieGroups) && $cookieGroups[array_key_first($cookieGroups)] instanceof CookieGroup) {
    //            return $cookieGroups;
    //        }
    //
    //        // LEGACY array shape -> convert to objects
    //        $normalized = [];
    //        foreach ($cookieGroups as $group) {
    //            $cookieGroup = new CookieGroup(
    //                (bool) ($group['isRequired'] ?? false),
    //                new CookieEntryCollection(array_values(array_map(function (array $cookieEntry): CookieEntry {
    //                    $entry = new CookieEntry((bool) ($cookieEntry['hidden'] ?? false));
    //                    $this->hydrateCookieStructFromArray($entry, data: $cookieEntry);
    //
    //                    return $entry;
    //                }, (array) ($group['entries'] ?? [])))),
    //            );
    //
    //            $this->hydrateCookieStructFromArray($cookieGroup, $group);
    //
    //            $normalized[] = $cookieGroup;
    //        }
    //
    //        return $normalized;
    //    }
    //
    //    /**
    //     * @param array<string, mixed> $data
    //     */
    //    private function hydrateCookieStructFromArray(CookieStruct $struct, array $data): void
    //    {
    //        $struct->snippetKeyName = $data['snippet_name'] ?? null;
    //        $struct->snippetKeyDescription = $data['snippet_description'] ?? null;
    //        $struct->cookie = $data['cookie'] ?? null;
    //        $struct->value = $data['value'] ?? null;
    //        $struct->expiration = $data['expiration'] ?? null;
    //    }

    public function filterCookieGroups(CookieGroupCollection $cookieGroups, SalesChannelContext $context): CookieGroupCollection
    {
        $cookieGroups = $this->filterGoogleAnalyticsCookie($context, $cookieGroups);
        $cookieGroups = $this->filterWishlistCookie($context->getSalesChannelId(), $cookieGroups);

        return $this->filterGoogleReCaptchaCookie($context->getSalesChannelId(), $cookieGroups);
    }

    /**
     * Translates the snippet names and descriptions of cookie groups and their entries.
     */
    public function translateCookieGroups(CookieGroupCollection $cookieGroups): void
    {
        foreach ($cookieGroups as $group) {
            $group->snippetKeyName = $this->translator->trans($group->snippetKeyName);

            if (isset($group->snippetKeyDescription)) {
                $group->snippetKeyDescription = $this->translator->trans($group->snippetKeyDescription);
            }

            $entries = $group->getEntries();
            if ($entries !== null) {
                foreach ($entries as $entry) {
                    if (isset($entry->snippetKeyName)) {
                        $entry->snippetKeyName = $this->translator->trans($entry->snippetKeyName);
                    }

                    if (isset($entry->snippetKeyDescription)) {
                        $entry->snippetKeyDescription = $this->translator->trans($entry->snippetKeyDescription);
                    }
                }
            }
        }
    }

    private function filterGoogleAnalyticsCookie(SalesChannelContext $context, CookieGroupCollection $cookieGroups): CookieGroupCollection
    {
        $salesChannel = $context->getSalesChannel();

        if ($salesChannel->getAnalytics() === null && $salesChannel->getAnalyticsId() !== null) {
            $criteria = new Criteria([$salesChannel->getAnalyticsId()]);
            $criteria->setTitle('cookie-controller::load-analytics');

            $salesChannel->setAnalytics(
                $this->salesChannelAnalyticsRepository->search($criteria, $context->getContext())->getEntities()->first()
            );
        }

        if ($salesChannel->getAnalytics()?->isActive()) {
            return $cookieGroups;
        }

        $filteredGroups = [];
        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup->snippetKeyName === 'cookie.groupStatistical') {
                $cookieGroup = $this->filterCookieGroup('google-analytics-enabled', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            if ($cookieGroup->snippetKeyName === 'cookie.groupMarketing') {
                $cookieGroup = $this->filterCookieGroup('google-ads-enabled', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            $filteredGroups[] = $cookieGroup;
        }

        return new CookieGroupCollection($filteredGroups);
    }

    private function filterWishlistCookie(string $salesChannelId, CookieGroupCollection $cookieGroups): CookieGroupCollection
    {
        if ($this->systemConfigService->getBool('core.cart.wishlistEnabled', $salesChannelId)) {
            return $cookieGroups;
        }

        $filteredGroups = [];
        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup->snippetKeyName === 'cookie.groupComfortFeatures') {
                $cookieGroup = $this->filterCookieGroup('wishlist-enabled', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            $filteredGroups[] = $cookieGroup;
        }

        return new CookieGroupCollection($filteredGroups);
    }

    private function filterGoogleReCaptchaCookie(string $salesChannelId, CookieGroupCollection $cookieGroups): CookieGroupCollection
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
            if ($cookieGroup->snippetKeyName === 'cookie.groupRequired') {
                $cookieGroup = $this->filterCookieGroup('_GRECAPTCHA', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            }

            $filteredGroups[] = $cookieGroup;
        }

        return new CookieGroupCollection($filteredGroups);
    }

    private function filterCookieGroup(string $cookieName, CookieGroup $cookieGroup): ?CookieGroup
    {
        $entries = $cookieGroup->getEntries();
        if (!$entries) {
            return null;
        }

        $cookieGroup->setEntries($entries->filter(function (CookieEntry $item) use ($cookieName) {
            return $item->cookie !== $cookieName;
        }));

        if (!$cookieGroup->getEntries() || \count($cookieGroup->getEntries()) === 0) {
            return null;
        }

        return $cookieGroup;
    }
}
