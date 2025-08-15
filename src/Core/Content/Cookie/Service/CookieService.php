<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Content\Cookie\Struct\CookieStruct;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Util\Hasher;
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
        $cookieGroups = $this->normalizeCookieGroups($cookieGroups);
        $cookieGroups = $this->filterCookieGroups($salesChannelContext, $cookieGroups);

        if ($translate) {
            $cookieGroups = $this->translateCookieGroups($cookieGroups);
        }

        return $this->convertToCookieGroupCollection($cookieGroups);
    }

    /**
     * Calculate a hash representing all cookie groups and their entries
     */
    public function calculateCookieHash(CookieGroupCollection $collection): string
    {
        $hashData = [];

        /** @var CookieGroup $cookieGroup */
        foreach ($collection->getElements() as $cookieGroup) {
            $groupData = [
                'isRequired' => $cookieGroup->isRequired,
                'snippetName' => $cookieGroup->snippetName ?? '',
                'snippetDescription' => $cookieGroup->snippetDescription ?? '',
                'cookie' => $cookieGroup->cookie ?? '',
                'value' => $cookieGroup->value ?? '',
                'expiration' => $cookieGroup->expiration ?? '',
                'entries' => [],
            ];

            foreach ($cookieGroup->entries as $entry) {
                $entryData = [
                    'hidden' => $entry->hidden,
                    'snippetName' => $entry->snippetName ?? '',
                    'snippetDescription' => $entry->snippetDescription ?? '',
                    'cookie' => $entry->cookie ?? '',
                    'value' => $entry->value ?? '',
                    'expiration' => $entry->expiration ?? '',
                ];

                $groupData['entries'][] = $entryData;
            }

            // Sort entries by their serialized content to ensure consistent hash
            usort($groupData['entries'], function ($a, $b) {
                return serialize($a) <=> serialize($b);
            });

            $hashData[] = $groupData;
        }

        // Sort groups by their serialized content to ensure consistent hash
        usort($hashData, function ($a, $b) {
            return serialize($a) <=> serialize($b);
        });

        // Generate SHA-1 hash of the serialized data
        return Hasher::hash(serialize($hashData), 'sha1');
    }

    /**
     * Converts an array of cookie groups to a CookieGroupCollection.
     *
     * @param array<string|int, CookieGroup> $cookieGroups
     */
    private function convertToCookieGroupCollection(array $cookieGroups): CookieGroupCollection
    {
        $collection = new CookieGroupCollection();
        foreach ($cookieGroups as $group) {
            $collection->add($group);
        }

        return $collection;
    }

    /**
     * @param array<string|int, mixed|CookieGroup> $cookieGroups // legacy arrays OR CookieGroup[]
     *
     * @return array<string|int, mixed|CookieGroup>
     *
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use CookieGroupCollection instead
     */
    private function normalizeCookieGroups(array $cookieGroups): array
    {
        // NEW typed shape (array of CookieGroup instances)
        if (!empty($cookieGroups) && $cookieGroups[array_key_first($cookieGroups)] instanceof CookieGroup) {
            return $cookieGroups;
        }

        // LEGACY array shape -> convert to objects
        $normalized = [];
        foreach ($cookieGroups as $group) {
            $cookieGroup = new CookieGroup(
                (bool) ($group['isRequired'] ?? false),
                array_values(array_map(function (array $cookieEntry): CookieEntry {
                    $entry = new CookieEntry((bool) ($cookieEntry['hidden'] ?? false));
                    $this->hydrateCookieStructFromArray($entry, data: $cookieEntry);

                    return $entry;
                }, (array) ($group['entries'] ?? []))),
            );

            $this->hydrateCookieStructFromArray($cookieGroup, $group);

            $normalized[] = $cookieGroup;
        }

        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use CookieGroupCollection instead')
        );

        return $normalized;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0. Use CookieStruct instead
     */
    private function hydrateCookieStructFromArray(CookieStruct $struct, array $data): void
    {
        $struct->snippetName = $data['snippet_name'] ?? null;
        $struct->snippetDescription = $data['snippet_description'] ?? null;
        $struct->cookie = $data['cookie'] ?? null;
        $struct->value = $data['value'] ?? null;
        $struct->expiration = $data['expiration'] ?? null;
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
     * Translates the snippet names and descriptions of cookie groups and their entries.
     *
     * @param array<string|int, CookieGroup> $cookieGroups
     *
     * @return array<string|int, CookieGroup>
     */
    private function translateCookieGroups(array $cookieGroups): array
    {
        foreach ($cookieGroups as $group) {
            if (isset($group->snippetName)) {
                $group->snippetName = $this->translator->trans($group->snippetName);
            }

            if (isset($group->snippetDescription)) {
                $group->snippetDescription = $this->translator->trans($group->snippetDescription);
            }

            if (isset($group->entries) && \is_array($group->entries)) {
                foreach ($group->entries as $entry) {
                    if (isset($entry->snippetName)) {
                        $entry->snippetName = $this->translator->trans($entry->snippetName);
                    }

                    if (isset($entry->snippetDescription)) {
                        $entry->snippetDescription = $this->translator->trans($entry->snippetDescription);
                    }
                }
            }
        }

        return $cookieGroups;
    }

    /**
     * @param array<string|int, CookieGroup> $cookieGroups
     *
     * @return array<string|int, CookieGroup>
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
            if ($cookieGroup->snippetName === 'cookie.groupStatistical') {
                $cookieGroup = $this->filterCookieGroup('cookie.groupStatisticalGoogleAnalytics', $cookieGroup);
                if ($cookieGroup !== null) {
                    $filteredGroups[] = $cookieGroup;
                }

                continue;
            } elseif ($cookieGroup->snippetName === 'cookie.groupMarketing') {
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

    private function filterCookieGroup(string $cookieSnippetName, ?CookieGroup $cookieGroup): ?CookieGroup
    {
        if ($cookieGroup === null) {
            return null;
        }

        $cookieGroup->entries = array_values(array_filter($cookieGroup->entries, function ($item) use ($cookieSnippetName) {
            return $item->snippetName !== $cookieSnippetName;
        }));

        if (\count($cookieGroup->entries) === 0) {
            return null;
        }

        return $cookieGroup;
    }

    /**
     * @param array<string|int, CookieGroup> $cookieGroups
     *
     * @return array<string|int, CookieGroup>
     */
    private function filterWishlistCookie(string $salesChannelId, array $cookieGroups): array
    {
        if ($this->systemConfigService->getBool('core.cart.wishlistEnabled', $salesChannelId)) {
            return $cookieGroups;
        }

        $filteredGroups = [];
        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup->snippetName === 'cookie.groupComfortFeatures') {
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
     * @param array<string|int, CookieGroup> $cookieGroups
     *
     * @return array<string|int, CookieGroup>
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
            if ($cookieGroup->snippetName === 'cookie.groupRequired') {
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
