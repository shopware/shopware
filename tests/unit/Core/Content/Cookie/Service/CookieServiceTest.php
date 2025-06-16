<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelAnalytics\SalesChannelAnalyticsCollection;
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
    public function testGetCookieGroupCollectionWithNoAnalytics(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.groupStatistical',
                'snippet_description' => 'Statistical cookies',
                'isRequired' => false,
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupStatisticalGoogleAnalytics',
                        'snippet_description' => 'Google Analytics',
                        'cookie' => 'google-analytics-enabled',
                        'value' => '1',
                        'expiration' => '30',
                        'hidden' => false,
                    ],
                ],
            ],
            [
                'snippet_name' => 'cookie.groupMarketing',
                'snippet_description' => 'Marketing cookies',
                'isRequired' => false,
                'entries' => [
                    [
                        'snippet_name' => 'cookie.groupMarketingAdConsent',
                        'snippet_description' => 'Google Ads',
                        'cookie' => 'google-ads-enabled',
                        'value' => '1',
                        'expiration' => '30',
                        'hidden' => false,
                    ],
                ],
            ],
            [
                'snippet_name' => 'cookie.groupOther',
                'snippet_description' => 'Other cookies',
                'isRequired' => false,
                'entries' => [
                    [
                        'snippet_name' => 'other.cookie',
                        'snippet_description' => 'Other cookie description',
                        'cookie' => 'other-cookie',
                        'value' => '1',
                        'expiration' => '30',
                        'hidden' => false,
                    ],
                ],
            ],
        ];

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function ($key) {
                return $key; // Return the key itself when no translation is found
            });

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->getCookieGroupCollection($cookieGroups, $salesChannelContext);

        // Should remove Google Analytics cookies but keep other groups
        static::assertCount(1, $result);
        $group = $result->first();
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('cookie.groupOther', $group->snippet_name);
        static::assertSame('Other cookies', $group->snippet_description);
        static::assertCount(1, $group->entries);
        static::assertSame('other.cookie', $group->entries[0]->snippet_name);
        static::assertSame('Other cookie description', $group->entries[0]->snippet_description);
        static::assertSame('other-cookie', $group->entries[0]->cookie);
        static::assertSame('1', $group->entries[0]->value);
        static::assertSame('30', $group->entries[0]->expiration);
    }

    public function testGetCookieGroupCollectionWithTranslation(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.group.test',
                'snippet_description' => 'cookie.group.test.description',
                'isRequired' => false,
                'entries' => [
                    [
                        'snippet_name' => 'cookie.entry.test',
                        'snippet_description' => 'cookie.entry.test.description',
                        'cookie' => 'test-cookie',
                        'value' => '1',
                        'expiration' => '30',
                        'hidden' => false,
                    ],
                ],
            ],
        ];

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function ($key) {
                return 'Translated: ' . $key;
            });

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->getCookieGroupCollection($cookieGroups, $salesChannelContext);

        static::assertCount(1, $result);
        $group = $result->first();
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('Translated: cookie.group.test', $group->snippet_name);
        static::assertSame('Translated: cookie.group.test.description', $group->snippet_description);
        static::assertCount(1, $group->entries);
        static::assertSame('Translated: cookie.entry.test', $group->entries[0]->snippet_name);
        static::assertSame('Translated: cookie.entry.test.description', $group->entries[0]->snippet_description);
        static::assertSame('test-cookie', $group->entries[0]->cookie);
        static::assertSame('1', $group->entries[0]->value);
        static::assertSame('30', $group->entries[0]->expiration);
    }

    public function testGetCookieGroupCollectionWithoutTranslation(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            [
                'snippet_name' => 'test.group',
                'snippet_description' => 'Test Group Description',
                'isRequired' => true,
                'entries' => [
                    [
                        'snippet_name' => 'test.cookie',
                        'snippet_description' => 'Test Cookie Description',
                        'cookie' => 'test-cookie',
                        'value' => '1',
                        'expiration' => '30',
                        'hidden' => false,
                    ],
                ],
            ],
        ];

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->getCookieGroupCollection($cookieGroups, $salesChannelContext, false);

        static::assertCount(1, $result);
        $group = $result->first();
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertTrue($group->isRequired);
        static::assertCount(1, $group->entries);
        static::assertSame('test.group', $group->snippet_name);
        static::assertSame('Test Group Description', $group->snippet_description);
        static::assertSame('test.cookie', $group->entries[0]->snippet_name);
        static::assertSame('Test Cookie Description', $group->entries[0]->snippet_description);
        static::assertSame('test-cookie', $group->entries[0]->cookie);
        static::assertSame('1', $group->entries[0]->value);
        static::assertSame('30', $group->entries[0]->expiration);
        static::assertFalse($group->entries[0]->hidden);
    }

    public function testJsonSerializationExcludesNullValues(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            [
                'snippet_name' => 'test.group',
                'snippet_description' => 'Test Group Description',
                'isRequired' => true,
                'entries' => [
                    [
                        'snippet_name' => 'test.cookie',
                        // Note: no snippet_description, cookie, value, or expiration provided
                        'hidden' => false,
                    ],
                ],
            ],
        ];

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function ($key) {
                return $key;
            });

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->getCookieGroupCollection($cookieGroups, $salesChannelContext);

        static::assertCount(1, $result);
        $group = $result->first();
        static::assertInstanceOf(CookieGroup::class, $group);

        // Test JSON serialization
        $groupJson = $group->jsonSerialize();
        $entryJson = $group->entries[0]->jsonSerialize();

        // Verify that null values are not present in the JSON output
        static::assertArrayNotHasKey('cookie', $groupJson);
        static::assertArrayNotHasKey('value', $groupJson);
        static::assertArrayNotHasKey('expiration', $groupJson);

        static::assertArrayNotHasKey('snippet_description', $entryJson);
        static::assertArrayNotHasKey('cookie', $entryJson);
        static::assertArrayNotHasKey('value', $entryJson);
        static::assertArrayNotHasKey('expiration', $entryJson);

        // Verify that non-null values are present
        static::assertArrayHasKey('snippet_name', $groupJson);
        static::assertArrayHasKey('snippet_description', $groupJson);
        static::assertArrayHasKey('isRequired', $groupJson);
        static::assertArrayHasKey('entries', $groupJson);

        static::assertArrayHasKey('snippet_name', $entryJson);
        static::assertArrayHasKey('hidden', $entryJson);
    }
}
