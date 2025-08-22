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
     */
    public function __construct(
        private SystemConfigService $systemConfigService,
        private EntityRepository $salesChannelAnalyticsRepository,
        private TranslatorInterface $translator
    ) {
    }

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
