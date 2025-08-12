<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Framework\Cookie;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Storefront\Framework\Cookie\CookieCollectionProvider;

/**
 * @internal
 */
#[CoversClass(CookieCollectionProvider::class)]
class CookieCollectionProviderTest extends TestCase
{
    private CookieCollectionProvider $cookieCollectionProvider;

    protected function setUp(): void
    {
        $this->cookieCollectionProvider = new CookieCollectionProvider();
    }

    public function testGetCookieGroups(): void
    {
        $cookieGroups = $this->cookieCollectionProvider->getCookieGroups();

        static::assertCount(4, $cookieGroups);

        $requiredGroup = $this->getGroup($cookieGroups, 'cookie.groupRequired', true, 4);
        $this->assertEntry($requiredGroup->entries[0], 'cookie.groupRequiredSession', 'session-', false);
        $this->assertEntry($requiredGroup->entries[1], 'cookie.groupRequiredTimezone', 'timezone', false);
        $this->assertEntry($requiredGroup->entries[2], 'cookie.groupRequiredAccepted', 'cookie-preference', true);
        $this->assertEntry($requiredGroup->entries[3], 'cookie.groupRequiredCaptcha', '_GRECAPTCHA', false);

        $statisticalGroup = $this->getGroup($cookieGroups, 'cookie.groupStatistical', false, 1);
        $this->assertEntry($statisticalGroup->entries[0], 'cookie.groupStatisticalGoogleAnalytics', 'google-analytics-enabled', false);

        $comfortGroup = $this->getGroup($cookieGroups, 'cookie.groupComfortFeatures', false, 2);
        $this->assertEntry($comfortGroup->entries[0], 'cookie.groupComfortFeaturesWishlist', 'wishlist-enabled', false);
        $this->assertEntry($comfortGroup->entries[1], 'cookie.groupComfortFeaturesYoutubeVideo', 'youtube-video', false);

        $marketingGroup = $this->getGroup($cookieGroups, 'cookie.groupMarketing', false, 1);
        $this->assertEntry($marketingGroup->entries[0], 'cookie.groupMarketingAdConsent', 'google-ads-enabled', false);
    }

    /**
     * @param array<CookieGroup> $cookieGroups
     */
    private function getGroup(array $cookieGroups, string $snippetName, bool $isRequired, int $entryCount): CookieGroup
    {
        $foundGroup = null;

        foreach ($cookieGroups as $cookieGroup) {
            if ($cookieGroup->snippetName === $snippetName) {
                $foundGroup = $cookieGroup;

                break;
            }
        }

        static::assertNotNull($foundGroup, \sprintf('Group with snippet name %s not found', $snippetName));
        static::assertInstanceOf(CookieGroup::class, $foundGroup);
        static::assertSame($isRequired, $foundGroup->isRequired);
        static::assertCount($entryCount, $foundGroup->entries);

        return $foundGroup;
    }

    private function assertEntry(CookieEntry $cookieEntry, string $snippetName, string $cookie, bool $hidden): void
    {
        static::assertSame($snippetName, $cookieEntry->snippetName);
        static::assertSame($cookie, $cookieEntry->cookie);
        static::assertSame($hidden, $cookieEntry->hidden);
    }
}
