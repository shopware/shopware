<?php declare(strict_types=1);

namespace Shopware\Storefront\Framework\Cookie;

use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\Feature;
use Shopware\Core\Framework\Log\Package;

#[Package('framework')]
class CookieCollectionProvider implements CookieProviderInterface, CookieCollectionProviderInterface
{
    /**
     * @deprecated tag:v6.8.0 - Will be removed in 6.8.0, replaced by getCookieGroupCollection method
     *
     * @return array<string|int, mixed>
     */
    public function getCookieGroups(): array
    {
        Feature::triggerDeprecationOrThrow(
            'v6.8.0.0',
            Feature::deprecatedMethodMessage(self::class, __METHOD__, 'v6.8.0.0', 'Use getCookieGroupCollection() instead')
        );
        // hint: the store API will only skip NULL values if the CookieGroupCollection is used
        if (Feature::isActive('v6.8.0.0')) {
            return $this->getCookieGroupCollection()->getElements();
        }

        // @deprecated tag:v6.8.0 - Will be removed in 6.8.0, replaced by the CookieCollectionProvider
        return (new CookieProvider())->getCookieGroups();
    }

    public function getCookieGroupCollection(): CookieGroupCollection
    {
        $cookieGroups = new CookieGroupCollection();

        $cookieGroups->add($this->getCookieGroupRequiredEntries());
        $cookieGroups->add($this->getCookieGroupStatistical());
        $cookieGroups->add($this->getCookieGroupComfortFeatures());
        $cookieGroups->add($this->getCookieGroupMarketing());

        return $cookieGroups;
    }

    private function getCookieGroupRequiredEntries(): CookieGroup
    {
        $cookieGroupRequired = new CookieGroup(
            true,
            new CookieEntryCollection([
                $this->getRequiredSessionEntry(),
                $this->getRequiredTimezoneEntry(),
                $this->getRequiredAcceptedEntry(),
                $this->getRequiredCaptchaEntry(),
            ]),
        );
        $cookieGroupRequired->snippetName = 'cookie.groupRequired';
        $cookieGroupRequired->snippetDescription = 'cookie.groupRequiredDescription';

        return $cookieGroupRequired;
    }

    private function getRequiredSessionEntry(): CookieEntry
    {
        $entryRequiredSession = new CookieEntry();
        $entryRequiredSession->snippetName = 'cookie.groupRequiredSession';
        $entryRequiredSession->cookie = 'session-';

        return $entryRequiredSession;
    }

    private function getRequiredTimezoneEntry(): CookieEntry
    {
        $entryRequiredTimezone = new CookieEntry();
        $entryRequiredTimezone->snippetName = 'cookie.groupRequiredTimezone';
        $entryRequiredTimezone->cookie = 'timezone';

        return $entryRequiredTimezone;
    }

    private function getRequiredAcceptedEntry(): CookieEntry
    {
        $entryRequiredAccepted = new CookieEntry();
        $entryRequiredAccepted->snippetName = 'cookie.groupRequiredAccepted';
        $entryRequiredAccepted->cookie = 'cookie-preference';
        $entryRequiredAccepted->value = '1';
        $entryRequiredAccepted->expiration = '30';
        $entryRequiredAccepted->hidden = true;

        return $entryRequiredAccepted;
    }

    private function getRequiredCaptchaEntry(): CookieEntry
    {
        $entryRequiredCaptcha = new CookieEntry();
        $entryRequiredCaptcha->snippetName = 'cookie.groupRequiredCaptcha';
        $entryRequiredCaptcha->cookie = '_GRECAPTCHA';
        $entryRequiredCaptcha->value = '1';

        return $entryRequiredCaptcha;
    }

    private function getCookieGroupStatistical(): CookieGroup
    {
        $cookieGroupStatistical = new CookieGroup(
            false,
            new CookieEntryCollection([
                $this->getGoogleAnalyticsEntry(),
            ]),
        );
        $cookieGroupStatistical->snippetName = 'cookie.groupStatistical';
        $cookieGroupStatistical->snippetDescription = 'cookie.groupStatisticalDescription';

        return $cookieGroupStatistical;
    }

    private function getGoogleAnalyticsEntry(): CookieEntry
    {
        $entryGoogleAnalytics = new CookieEntry();
        $entryGoogleAnalytics->snippetName = 'cookie.groupStatisticalGoogleAnalytics';
        $entryGoogleAnalytics->cookie = 'google-analytics-enabled';
        $entryGoogleAnalytics->value = '1';
        $entryGoogleAnalytics->expiration = '30';

        return $entryGoogleAnalytics;
    }

    private function getCookieGroupComfortFeatures(): CookieGroup
    {
        $cookieGroupComfortFeatures = new CookieGroup(
            false,
            new CookieEntryCollection([
                $this->getWishlistEntry(),
                $this->getYoutubeVideoEntry(),
            ]),
        );
        $cookieGroupComfortFeatures->snippetName = 'cookie.groupComfortFeatures';

        return $cookieGroupComfortFeatures;
    }

    private function getWishlistEntry(): CookieEntry
    {
        $entryWishlist = new CookieEntry();
        $entryWishlist->snippetName = 'cookie.groupComfortFeaturesWishlist';
        $entryWishlist->cookie = 'wishlist-enabled';
        $entryWishlist->value = '1';
        $entryWishlist->expiration = '30';

        return $entryWishlist;
    }

    private function getYoutubeVideoEntry(): CookieEntry
    {
        $entryYoutubeVideo = new CookieEntry();
        $entryYoutubeVideo->snippetName = 'cookie.groupComfortFeaturesYoutubeVideo';
        $entryYoutubeVideo->cookie = 'youtube-video';
        $entryYoutubeVideo->value = '1';
        $entryYoutubeVideo->expiration = '30';

        return $entryYoutubeVideo;
    }

    private function getCookieGroupMarketing(): CookieGroup
    {
        $cookieGroupMarketing = new CookieGroup(
            false,
            new CookieEntryCollection([
                $this->getGoogleAdsEntry(),
            ]),
        );
        $cookieGroupMarketing->snippetName = 'cookie.groupMarketing';
        $cookieGroupMarketing->snippetDescription = 'cookie.groupMarketingDescription';

        return $cookieGroupMarketing;
    }

    private function getGoogleAdsEntry(): CookieEntry
    {
        $entryGoogleAds = new CookieEntry();
        $entryGoogleAds->snippetName = 'cookie.groupMarketingAdConsent';
        $entryGoogleAds->cookie = 'google-ads-enabled';
        $entryGoogleAds->value = '1';
        $entryGoogleAds->expiration = '30';

        return $entryGoogleAds;
    }
}
