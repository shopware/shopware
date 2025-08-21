<?php declare(strict_types=1);

namespace Shopware\Core\Content\Cookie\Service;

use Shopware\Core\Content\Cookie\Event\CookieGroupCollectEvent;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Package('framework')]
class CookieProvider
{
    private readonly string $sessionName;

    /**
     * @internal
     *
     * @param array<string, mixed> $sessionOptions
     */
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        array $sessionOptions = [],
    ) {
        $this->sessionName = $sessionOptions['name'] ?? PlatformRequest::FALLBACK_SESSION_NAME;
    }

    public function getCookieGroups(SalesChannelContext $salesChannelContext): CookieGroupCollection
    {
        $cookieGroups = new CookieGroupCollection();

        $cookieGroups->add($this->getCookieGroupRequiredEntries());
        $cookieGroups->add($this->getCookieGroupStatistical());
        $cookieGroups->add($this->getCookieGroupComfortFeatures());
        $cookieGroups->add($this->getCookieGroupMarketing());

        return $this->eventDispatcher->dispatch(new CookieGroupCollectEvent($cookieGroups, $salesChannelContext))->cookieGroupCollection;
    }

    private function getCookieGroupRequiredEntries(): CookieGroup
    {
        $cookieGroupRequired = new CookieGroup('cookie.groupRequired');
        $cookieGroupRequired->snippetDescription = 'cookie.groupRequiredDescription';
        $cookieGroupRequired->entries = new CookieEntryCollection([
            $this->getRequiredSessionEntry(),
            $this->getRequiredTimezoneEntry(),
            $this->getRequiredAcceptedEntry(),
            $this->getRequiredCaptchaEntry(),
        ]);
        $cookieGroupRequired->isRequired = true;

        return $cookieGroupRequired;
    }

    private function getRequiredSessionEntry(): CookieEntry
    {
        $entryRequiredSession = new CookieEntry($this->sessionName);
        $entryRequiredSession->snippetName = 'cookie.groupRequiredSession';

        return $entryRequiredSession;
    }

    private function getRequiredTimezoneEntry(): CookieEntry
    {
        $entryRequiredTimezone = new CookieEntry('timezone');
        $entryRequiredTimezone->snippetName = 'cookie.groupRequiredTimezone';

        return $entryRequiredTimezone;
    }

    private function getRequiredAcceptedEntry(): CookieEntry
    {
        $entryRequiredAccepted = new CookieEntry('cookie-preference');
        $entryRequiredAccepted->snippetName = 'cookie.groupRequiredAccepted';
        $entryRequiredAccepted->value = '1';
        $entryRequiredAccepted->expiration = 30;
        $entryRequiredAccepted->hidden = true;

        return $entryRequiredAccepted;
    }

    private function getRequiredCaptchaEntry(): CookieEntry
    {
        $entryRequiredCaptcha = new CookieEntry('_GRECAPTCHA');
        $entryRequiredCaptcha->snippetName = 'cookie.groupRequiredCaptcha';
        $entryRequiredCaptcha->value = '1';

        return $entryRequiredCaptcha;
    }

    private function getCookieGroupStatistical(): CookieGroup
    {
        $cookieGroupStatistical = new CookieGroup('cookie.groupStatistical');
        $cookieGroupStatistical->entries = new CookieEntryCollection([
            $this->getGoogleAnalyticsEntry(),
        ]);
        $cookieGroupStatistical->snippetDescription = 'cookie.groupStatisticalDescription';

        return $cookieGroupStatistical;
    }

    private function getGoogleAnalyticsEntry(): CookieEntry
    {
        $entryGoogleAnalytics = new CookieEntry('google-analytics-enabled');
        $entryGoogleAnalytics->snippetName = 'cookie.groupStatisticalGoogleAnalytics';
        $entryGoogleAnalytics->value = '1';
        $entryGoogleAnalytics->expiration = 30;

        return $entryGoogleAnalytics;
    }

    private function getCookieGroupComfortFeatures(): CookieGroup
    {
        $cookieGroupComfortFeatures = new CookieGroup('cookie.groupComfortFeatures');
        $cookieGroupComfortFeatures->entries = new CookieEntryCollection([
            $this->getWishlistEntry(),
            $this->getYoutubeVideoEntry(),
        ]);

        return $cookieGroupComfortFeatures;
    }

    private function getWishlistEntry(): CookieEntry
    {
        $entryWishlist = new CookieEntry('wishlist-enabled');
        $entryWishlist->snippetName = 'cookie.groupComfortFeaturesWishlist';
        $entryWishlist->value = '1';
        $entryWishlist->expiration = 30;

        return $entryWishlist;
    }

    private function getYoutubeVideoEntry(): CookieEntry
    {
        $entryYoutubeVideo = new CookieEntry('youtube-video');
        $entryYoutubeVideo->snippetName = 'cookie.groupComfortFeaturesYoutubeVideo';
        $entryYoutubeVideo->value = '1';
        $entryYoutubeVideo->expiration = 30;

        return $entryYoutubeVideo;
    }

    private function getCookieGroupMarketing(): CookieGroup
    {
        $cookieGroupMarketing = new CookieGroup('cookie.groupMarketing');
        $cookieGroupMarketing->snippetDescription = 'cookie.groupMarketingDescription';
        $cookieGroupMarketing->entries = new CookieEntryCollection([
            $this->getGoogleAdsEntry(),
        ]);

        return $cookieGroupMarketing;
    }

    private function getGoogleAdsEntry(): CookieEntry
    {
        $entryGoogleAds = new CookieEntry('google-ads-enabled');
        $entryGoogleAds->snippetName = 'cookie.groupMarketingAdConsent';
        $entryGoogleAds->value = '1';
        $entryGoogleAds->expiration = 30;

        return $entryGoogleAds;
    }
}
