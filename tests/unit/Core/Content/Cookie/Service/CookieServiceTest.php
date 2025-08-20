<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
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
            (new CookieGroup(
                isRequired: false,
                entries: new CookieEntryCollection([
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'cookie.groupStatisticalGoogleAnalytics',
                        'snippetDescription' => 'Google Analytics',
                        'cookie' => 'google-analytics-enabled',
                        'value' => '1',
                        'expiration' => '30',
                    ]),
                ]),
            ))->assign([
                'snippetName' => 'cookie.groupStatistical',
                'snippetDescription' => 'Statistical cookies',
            ]),
            (new CookieGroup(
                isRequired: false,
                entries: new CookieEntryCollection([
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'cookie.groupMarketingAdConsent',
                        'snippetDescription' => 'Google Ads',
                        'cookie' => 'google-ads-enabled',
                        'value' => '1',
                        'expiration' => '30',
                    ]),
                ]),
            ))->assign([
                'snippetName' => 'cookie.groupMarketing',
                'snippetDescription' => 'Marketing cookies',
            ]),
            (new CookieGroup(
                isRequired: false,
                entries: new CookieEntryCollection([
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'other.cookie',
                        'snippetDescription' => 'Other cookie description',
                        'cookie' => 'other-cookie',
                        'value' => '1',
                        'expiration' => '30',
                    ]),
                ]),
            ))->assign([
                'snippetName' => 'cookie.groupOther',
                'snippetDescription' => 'Other cookies',
            ]),
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
        static::assertSame('cookie.groupOther', $group->snippetName);
        static::assertSame('Other cookies', $group->snippetDescription);
        static::assertCount(1, $group->entries);
        static::assertSame('other.cookie', $group->entries->first()?->snippetName);
        static::assertSame('Other cookie description', $group->entries->first()->snippetDescription);
        static::assertSame('other-cookie', $group->entries->first()->cookie);
        static::assertSame('1', $group->entries->first()->value);
        static::assertSame('30', $group->entries->first()->expiration);
    }

    public function testGetCookieGroupCollectionWithTranslation(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroupEntry = (new CookieEntry(
            hidden: false,
        ))->assign([
            'snippetName' => 'cookie.entry.test',
            'snippetDescription' => 'cookie.entry.test.description',
            'cookie' => 'test-cookie',
            'value' => '1',
            'expiration' => '30',
        ]);

        $cookieGroup = (new CookieGroup(
            isRequired: false,
            entries: new CookieEntryCollection([
                $cookieGroupEntry,
            ]),
        ))->assign([
            'snippetName' => 'cookie.group.test',
            'snippetDescription' => 'cookie.group.test.description',
        ]);

        $cookieGroups = [
            $cookieGroup,
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
        static::assertSame('Translated: cookie.group.test', $group->snippetName);
        static::assertSame('Translated: cookie.group.test.description', $group->snippetDescription);
        static::assertCount(1, $group->entries);
        static::assertSame('Translated: cookie.entry.test', $group->entries->first()?->snippetName);
        static::assertSame('Translated: cookie.entry.test.description', $group->entries->first()->snippetDescription);
        static::assertSame('test-cookie', $group->entries->first()->cookie);
        static::assertSame('1', $group->entries->first()->value);
        static::assertSame('30', $group->entries->first()->expiration);
    }

    public function testGetCookieGroupCollectionWithoutTranslation(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            (new CookieGroup(
                isRequired: true,
                entries: new CookieEntryCollection([
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'test.cookie',
                        'snippetDescription' => 'Test Cookie Description',
                        'cookie' => 'test-cookie',
                        'value' => '1',
                        'expiration' => '30',
                    ]),
                ]),
            ))->assign([
                'snippetName' => 'test.group',
                'snippetDescription' => 'Test Group Description',
            ]),
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
        static::assertSame('test.group', $group->snippetName);
        static::assertSame('Test Group Description', $group->snippetDescription);
        static::assertSame('test.cookie', $group->entries->first()?->snippetName);
        static::assertSame('Test Cookie Description', $group->entries->first()->snippetDescription);
        static::assertSame('test-cookie', $group->entries->first()->cookie);
        static::assertSame('1', $group->entries->first()->value);
        static::assertSame('30', $group->entries->first()->expiration);
        static::assertFalse($group->entries->first()->hidden);
    }

    public function testJsonSerialization(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            (new CookieGroup(
                isRequired: true,
                entries: new CookieEntryCollection([
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'test.cookie',
                        'snippetDescription' => null,
                        'cookie' => null,
                        'value' => null,
                        'expiration' => null,
                    ]),
                ]),
            ))->assign([
                'snippetName' => 'test.group',
                'snippetDescription' => 'Test Group Description',
            ]),
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
        $entryJson = $group->entries->first()?->jsonSerialize();
        static::assertNotNull($entryJson);

        static::assertArrayHasKey('snippetName', $groupJson);
        static::assertArrayHasKey('snippetDescription', $groupJson);
        static::assertArrayHasKey('isRequired', $groupJson);
        static::assertArrayHasKey('entries', $groupJson);

        static::assertArrayHasKey('snippetName', $entryJson);
        static::assertArrayHasKey('hidden', $entryJson);
    }
}
