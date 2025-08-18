<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
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
                entries: [
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'cookie.groupStatisticalGoogleAnalytics',
                        'snippetDescription' => 'Google Analytics',
                        'cookie' => 'google-analytics-enabled',
                        'value' => '1',
                        'expiration' => '30',
                    ]),
                ],
            ))->assign([
                'snippetName' => 'cookie.groupStatistical',
                'snippetDescription' => 'Statistical cookies',
            ]),
            (new CookieGroup(
                isRequired: false,
                entries: [
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'cookie.groupMarketingAdConsent',
                        'snippetDescription' => 'Google Ads',
                        'cookie' => 'google-ads-enabled',
                        'value' => '1',
                        'expiration' => '30',
                    ]),
                ],
            ))->assign([
                'snippetName' => 'cookie.groupMarketing',
                'snippetDescription' => 'Marketing cookies',
            ]),
            (new CookieGroup(
                isRequired: false,
                entries: [
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'other.cookie',
                        'snippetDescription' => 'Other cookie description',
                        'cookie' => 'other-cookie',
                        'value' => '1',
                        'expiration' => '30',
                    ]),
                ],
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
        static::assertSame('other.cookie', $group->entries[0]->snippetName);
        static::assertSame('Other cookie description', $group->entries[0]->snippetDescription);
        static::assertSame('other-cookie', $group->entries[0]->cookie);
        static::assertSame('1', $group->entries[0]->value);
        static::assertSame('30', $group->entries[0]->expiration);
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
            entries: [
                $cookieGroupEntry,
            ]
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
        static::assertSame('Translated: cookie.entry.test', $group->entries[0]->snippetName);
        static::assertSame('Translated: cookie.entry.test.description', $group->entries[0]->snippetDescription);
        static::assertSame('test-cookie', $group->entries[0]->cookie);
        static::assertSame('1', $group->entries[0]->value);
        static::assertSame('30', $group->entries[0]->expiration);
    }

    public function testGetCookieGroupCollectionWithoutTranslation(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            (new CookieGroup(
                isRequired: true,
                entries: [
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'test.cookie',
                        'snippetDescription' => 'Test Cookie Description',
                        'cookie' => 'test-cookie',
                        'value' => '1',
                        'expiration' => '30',
                    ]),
                ],
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
        static::assertSame('test.cookie', $group->entries[0]->snippetName);
        static::assertSame('Test Cookie Description', $group->entries[0]->snippetDescription);
        static::assertSame('test-cookie', $group->entries[0]->cookie);
        static::assertSame('1', $group->entries[0]->value);
        static::assertSame('30', $group->entries[0]->expiration);
        static::assertFalse($group->entries[0]->hidden);
    }

    public function testJsonSerialization(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            (new CookieGroup(
                isRequired: true,
                entries: [
                    (new CookieEntry(
                        hidden: false,
                    ))->assign([
                        'snippetName' => 'test.cookie',
                        'snippetDescription' => null,
                        'cookie' => null,
                        'value' => null,
                        'expiration' => null,
                    ]),
                ],
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
        $entryJson = $group->entries[0]->jsonSerialize();

        static::assertArrayHasKey('snippetName', $groupJson);
        static::assertArrayHasKey('snippetDescription', $groupJson);
        static::assertArrayHasKey('isRequired', $groupJson);
        static::assertArrayHasKey('entries', $groupJson);

        static::assertArrayHasKey('snippetName', $entryJson);
        static::assertArrayHasKey('hidden', $entryJson);
    }

    public function testCalculateCookieHashWithEmptyCollection(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $collection = new CookieGroupCollection();

        $hash = $cookieService->calculateCookieHash($collection);

        static::assertIsString($hash);
        static::assertSame(40, \strlen($hash)); // SHA-1 produces 40 character hex string
        static::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $hash);
    }

    public function testCalculateCookieHashConsistency(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);

        // Create two identical collections
        $collection1 = $this->createTestCollection();
        $collection2 = $this->createTestCollection();

        $hash1 = $cookieService->calculateCookieHash($collection1);
        $hash2 = $cookieService->calculateCookieHash($collection2);

        // Same data should produce same hash
        static::assertSame($hash1, $hash2);
    }

    public function testCalculateCookieHashChangesWithDifferentData(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);

        $collection1 = $this->createTestCollection();

        // Create a different collection
        $collection2 = new CookieGroupCollection();
        $entry = new CookieEntry(hidden: false);
        $entry->cookie = 'different-cookie';
        $group = new CookieGroup(isRequired: false, entries: [$entry]);
        $group->snippetName = 'different.group';
        $collection2->add($group);

        $hash1 = $cookieService->calculateCookieHash($collection1);
        $hash2 = $cookieService->calculateCookieHash($collection2);

        // Different data should produce different hashes
        static::assertNotSame($hash1, $hash2);
    }

    public function testCalculateCookieHashHandlesNullValues(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);

        $collection = new CookieGroupCollection();
        $entry = new CookieEntry(hidden: true);
        // Leave all optional fields as null
        $group = new CookieGroup(isRequired: true, entries: [$entry]);
        $collection->add($group);

        $hash = $cookieService->calculateCookieHash($collection);

        static::assertIsString($hash);
        static::assertSame(40, \strlen($hash));
        static::assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $hash);
    }

    public function testCalculateCookieHashOrderIndependent(): void
    {
        $repository = $this->createMock(EntityRepository::class);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);

        $cookieService = new CookieService($systemConfigService, $repository, $translator);

        // Create collections with same groups but in different order
        $collection1 = new CookieGroupCollection();
        $collection2 = new CookieGroupCollection();

        $group1 = new CookieGroup(isRequired: true, entries: []);
        $group1->snippetName = 'group1';

        $group2 = new CookieGroup(isRequired: false, entries: []);
        $group2->snippetName = 'group2';

        // Add in different order
        $collection1->add($group1);
        $collection1->add($group2);

        $collection2->add($group2);
        $collection2->add($group1);

        $hash1 = $cookieService->calculateCookieHash($collection1);
        $hash2 = $cookieService->calculateCookieHash($collection2);

        // Order should not matter due to sorting in the algorithm
        static::assertSame($hash1, $hash2);
    }

    private function createTestCollection(): CookieGroupCollection
    {
        $collection = new CookieGroupCollection();

        $entry = new CookieEntry(hidden: false);
        $entry->cookie = 'test-cookie';
        $entry->snippetName = 'test.cookie';
        $entry->snippetDescription = 'Test Cookie Description';
        $entry->value = '1';
        $entry->expiration = '30';

        $group = new CookieGroup(isRequired: true, entries: [$entry]);
        $group->snippetName = 'test.group';
        $group->snippetDescription = 'Test Group Description';
        $group->cookie = 'test-group-cookie';
        $group->value = 'group-value';
        $group->expiration = '60';

        $collection->add($group);

        return $collection;
    }
}
