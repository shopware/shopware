<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsEntity;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * @internal
 */
#[CoversClass(CookieService::class)]
class CookieServiceTest extends TestCase
{
    public function testFilterGoogleAnalyticsCookieWithNoAnalytics(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupStatistical',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupStatisticalGoogleAnalytics',
                        'cookie' => 'google-analytics-enabled',
                    ],
                ],
            ],
            [
                'snippet_name' => 'cookie.groupMarketing',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupMarketingAdConsent',
                        'cookie' => 'google-ads-enabled',
                    ],
                ],
            ],
            [
                'snippet_name' => 'cookie.groupOther',
                'entries' => [
                    [
                        'snippet_name' => 'other.cookie',
                        'cookie' => 'other-cookie',
                    ],
                ],
            ],
        ];

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterGoogleAnalyticsCookie($salesChannelContext, $cookieGroups);

        // Should remove Google Analytics cookies but keep other groups
        static::assertCount(1, $result);
        static::assertSame('cookie.groupOther', $result[0]['snippet_name']);
    }

    public function testFilterGoogleAnalyticsCookieWithActiveAnalytics(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);

        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        $analytics->setActive(true);

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupStatistical',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupStatisticalGoogleAnalytics',
                        'cookie' => 'google-analytics-enabled',
                    ],
                ],
            ],
        ];

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterGoogleAnalyticsCookie($salesChannelContext, $cookieGroups);

        // Should keep all groups when analytics is active
        static::assertCount(1, $result);
        static::assertSame('cookie.groupStatistical', $result[0]['snippet_name']);
    }

    public function testFilterGoogleAnalyticsCookieWithInactiveAnalytics(): void
    {
        $analyticsId = Uuid::randomHex();
        $salesChannelContext = Generator::generateSalesChannelContext();
        $salesChannelContext->getSalesChannel()->setAnalyticsId($analyticsId);

        $analytics = new SalesChannelAnalyticsEntity();
        $analytics->setId($analyticsId);
        $analytics->setActive(false);

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupStatistical',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupStatisticalGoogleAnalytics',
                        'cookie' => 'google-analytics-enabled',
                    ],
                ],
            ],
        ];

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([$analytics])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterGoogleAnalyticsCookie($salesChannelContext, $cookieGroups);

        // Should remove Google Analytics cookies when analytics is inactive
        static::assertEmpty($result);
    }

    public function testFilterWishlistCookieWhenEnabled(): void
    {
        $salesChannelId = Uuid::randomHex();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupComfortFeatures',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupComfortFeaturesWishlist',
                        'cookie' => 'wishlist-enabled',
                    ],
                    [
                        'snippet_name' => 'other.feature',
                        'cookie' => 'other-feature',
                    ],
                ],
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->with('core.cart.wishlistEnabled', $salesChannelId)
            ->willReturn(true);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterWishlistCookie($salesChannelId, $cookieGroups);

        // Should keep all cookies when wishlist is enabled
        static::assertCount(1, $result);
        static::assertCount(2, $result[0]['entries']);
    }

    public function testFilterWishlistCookieWhenDisabled(): void
    {
        $salesChannelId = Uuid::randomHex();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupComfortFeatures',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupComfortFeaturesWishlist',
                        'cookie' => 'wishlist-enabled',
                    ],
                    [
                        'snippet_name' => 'other.feature',
                        'cookie' => 'other-feature',
                    ],
                ],
            ],
            [
                'snippet_name' => 'cookie.groupOther',
                'entries' => [
                    [
                        'snippet_name' => 'other.cookie',
                        'cookie' => 'other-cookie',
                    ],
                ],
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->with('core.cart.wishlistEnabled', $salesChannelId)
            ->willReturn(false);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterWishlistCookie($salesChannelId, $cookieGroups);

        // Should remove wishlist cookie but keep other entries and groups
        static::assertCount(2, $result);
        static::assertSame('cookie.groupComfortFeatures', $result[0]['snippet_name']);
        static::assertCount(1, $result[0]['entries']); // Only other.feature should remain

        // Get the filtered entries and check the first (and only) remaining entry
        $remainingEntries = array_values($result[0]['entries']);
        static::assertSame('other.feature', $remainingEntries[0]['snippet_name']);
        static::assertSame('cookie.groupOther', $result[1]['snippet_name']);
    }

    public function testFilterWishlistCookieRemovesEmptyGroup(): void
    {
        $salesChannelId = Uuid::randomHex();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupComfortFeatures',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupComfortFeaturesWishlist',
                        'cookie' => 'wishlist-enabled',
                    ],
                ],
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->with('core.cart.wishlistEnabled', $salesChannelId)
            ->willReturn(false);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterWishlistCookie($salesChannelId, $cookieGroups);

        // Should remove the entire group when all entries are filtered out
        static::assertEmpty($result);
    }

    public function testFilterGoogleReCaptchaCookieWhenEnabled(): void
    {
        $salesChannelId = Uuid::randomHex();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupRequired',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupRequiredCaptcha',
                        'cookie' => 'google-recaptcha',
                    ],
                ],
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->willReturnCallback(function (string $key, ?string $salesChannelId = null) {
                if ($key === 'core.basicInformation.activeCaptchasV2.googleReCaptchaV2.isActive') {
                    return true;
                }

                return false;
            });

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterGoogleReCaptchaCookie($salesChannelId, $cookieGroups);

        // Should keep all cookies when reCaptcha is enabled
        static::assertCount(1, $result);
        static::assertSame('cookie.groupRequired', $result[0]['snippet_name']);
    }

    public function testFilterGoogleReCaptchaCookieWhenDisabled(): void
    {
        $salesChannelId = Uuid::randomHex();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupRequired',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupRequiredCaptcha',
                        'cookie' => 'google-recaptcha',
                    ],
                    [
                        'snippet_name' => 'other.required',
                        'cookie' => 'other-required',
                    ],
                ],
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')
            ->willReturn(false); // All captcha configs return false

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterGoogleReCaptchaCookie($salesChannelId, $cookieGroups);

        // Should remove reCaptcha cookie but keep other entries
        static::assertCount(1, $result);
        static::assertSame('cookie.groupRequired', $result[0]['snippet_name']);
        static::assertCount(1, $result[0]['entries']);

        // Get the filtered entries and check the remaining entry
        $remainingEntries = array_values($result[0]['entries']);
        static::assertSame('other.required', $remainingEntries[0]['snippet_name']);
    }

    public function testConvertToCookieGroupCollection(): void
    {
        $cookieGroups = [
            [
                'snippet_name' => 'Test Group',
                'snippet_description' => 'Test Description',
                'isRequired' => true,
                'entries' => [
                    [
                        'snippet_name' => 'Test Cookie',
                        'cookie' => 'test-cookie',
                        'value' => 'test-value',
                        'expiration' => '30',
                        'hidden' => false,
                    ],
                ],
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->convertToCookieGroupCollection($cookieGroups);

        static::assertCount(1, $result);

        $group = $result->first();
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertTrue($group->isRequired);
        static::assertSame('Test Group', $group->getSnippetName());
        static::assertSame('Test Description', $group->getSnippetDescription());

        $entries = $group->getEntries();
        static::assertCount(1, $entries);

        $entry = $entries[0];
        static::assertFalse($entry->hidden);
        static::assertSame('Test Cookie', $entry->getSnippetName());
        static::assertSame('test-cookie', $entry->getCookie());
        static::assertSame('test-value', $entry->getValue());
        static::assertSame('30', $entry->getExpiration());
    }

    public function testConvertToCookieGroupCollectionWithEmptyEntries(): void
    {
        $cookieGroups = [
            [
                'snippet_name' => 'Empty Group',
                'isRequired' => false,
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->convertToCookieGroupCollection($cookieGroups);

        static::assertCount(1, $result);

        $group = $result->first();
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertFalse($group->isRequired);
        static::assertSame('Empty Group', $group->getSnippetName());
        static::assertEmpty($group->getEntries());
    }

    public function testFilterCookieGroup(): void
    {
        $cookieGroup = [
            'snippet_name' => 'Test Group',
            'entries' => [
                [
                    'snippet_name' => 'cookie.toRemove',
                    'cookie' => 'remove-me',
                ],
                [
                    'snippet_name' => 'cookie.toKeep',
                    'cookie' => 'keep-me',
                ],
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterCookieGroup('cookie.toRemove', $cookieGroup);

        static::assertNotNull($result);
        static::assertCount(1, $result['entries']);

        // Get the filtered entries and check the remaining entry
        $remainingEntries = array_values($result['entries']);
        static::assertSame('cookie.toKeep', $remainingEntries[0]['snippet_name']);
    }

    public function testFilterCookieGroupReturnsNullWhenEmpty(): void
    {
        $cookieGroup = [
            'snippet_name' => 'Test Group',
            'entries' => [
                [
                    'snippet_name' => 'cookie.toRemove',
                    'cookie' => 'remove-me',
                ],
            ],
        ];

        $systemConfigService = $this->createMock(SystemConfigService::class);
        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterCookieGroup('cookie.toRemove', $cookieGroup);

        static::assertNull($result);
    }
}
