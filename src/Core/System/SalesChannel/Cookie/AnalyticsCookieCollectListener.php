<?php

declare(strict_types=1);

namespace Shopware\Core\System\SalesChannel\Cookie;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Service\CookieProvider;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;

/**
 * @internal
 */
#[Package('discovery')]
class AnalyticsCookieCollectListener
{
    /**
     * @param EntityRepository<SalesChannelAnalyticsCollection> $salesChannelAnalyticsRepository
     */
    public function __construct(
        private readonly EntityRepository $salesChannelAnalyticsRepository,
    ) {
    }

    public function __invoke(CookieGroupCollectEvent $event): void
    {
        $salesChannel = $event->getSalesChannelContext()->getSalesChannel();

        $analyticsId = $salesChannel->getAnalyticsId();
        if ($analyticsId === null) {
            return;
        }

        $analytics = $salesChannel->getAnalytics();
        if ($analytics === null) {
            $criteria = new Criteria([$analyticsId]);
            $criteria->setTitle('analytics-cookie-collect-listener::load-analytics');

            $analytics = $this->salesChannelAnalyticsRepository->search($criteria, $event->getContext())->getEntities()->get($analyticsId);
        }

        if (!$analytics?->isActive()) {
            return;
        }

        $this->handleStatisticalGroup($event->cookieGroupCollection);
        $this->handleMarketingGroup($event->cookieGroupCollection);
    }

    private function handleStatisticalGroup(CookieGroupCollection $cookieGroupCollection): void
    {
        $statisticalCookieGroup = $cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_STATISTICAL);
        if (!$statisticalCookieGroup) {
            return;
        }

        $entries = $statisticalCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $statisticalCookieGroup->setEntries($entries);
        }

        $entryGoogleAnalytics = new CookieEntry('google-analytics-enabled');
        $entryGoogleAnalytics->name = 'cookie.groupStatisticalGoogleAnalytics';
        $entryGoogleAnalytics->value = '1';
        $entryGoogleAnalytics->expiration = 30;

        $entries->add($entryGoogleAnalytics);
    }

    private function handleMarketingGroup(CookieGroupCollection $cookieGroupCollection): void
    {
        $marketingCookieGroup = $cookieGroupCollection->get(CookieProvider::SNIPPET_NAME_COOKIE_GROUP_MARKETING);
        if (!$marketingCookieGroup) {
            return;
        }

        $entries = $marketingCookieGroup->getEntries();
        if ($entries === null) {
            $entries = new CookieEntryCollection();
            $marketingCookieGroup->setEntries($entries);
        }

        $entryGoogleAds = new CookieEntry('google-ads-enabled');
        $entryGoogleAds->name = 'cookie.groupMarketingAdConsent';
        $entryGoogleAds->value = '1';
        $entryGoogleAds->expiration = 30;

        $entries->add($entryGoogleAds);
    }
}
