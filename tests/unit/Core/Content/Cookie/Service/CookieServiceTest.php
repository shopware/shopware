<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Cookie\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Cookie\Service\CookieService;
use Shopware\Core\Content\Cookie\Struct\CookieEntry;
use Shopware\Core\Content\Cookie\Struct\CookieEntryCollection;
use Shopware\Core\Content\Cookie\Struct\CookieGroup;
use Shopware\Core\Content\Cookie\Struct\CookieGroupCollection;
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
        $statisticalGroup = new CookieGroup('cookie.groupStatistical');
        $statisticalGroup->setEntries(new CookieEntryCollection([new CookieEntry('google-analytics-enabled')]));

        $marketingGroup = new CookieGroup('cookie.groupMarketing');
        $marketingGroup->setEntries(new CookieEntryCollection([new CookieEntry('google-ads-enabled')]));

        $otherGroup = new CookieGroup('cookie.groupOther');
        $otherGroup->setEntries(new CookieEntryCollection([new CookieEntry('other-cookie')]));

        $cookieGroups = new CookieGroupCollection([$statisticalGroup, $marketingGroup, $otherGroup]);

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function ($key) {
                return $key; // Return the key itself when no translation is found
            });

        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $result = $cookieService->filterCookieGroups($cookieGroups, Generator::generateSalesChannelContext());

        // Should remove Google Analytics cookies but keep other groups
        static::assertCount(1, $result);
        $group = $result->get('cookie.groupOther');
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('cookie.groupOther', $group->snippetKeyName);
        $entries = $group->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        static::assertSame('other-cookie', $entries->get('other-cookie')?->cookie);
    }

    public function testGetCookieGroupCollectionWithTranslation(): void
    {
        $cookieGroupEntry = new CookieEntry('test-cookie');
        $cookieGroupEntry->snippetKeyName = 'cookie.entry.test';
        $cookieGroupEntry->snippetKeyDescription = 'cookie.entry.test.description';

        $cookieGroup = new CookieGroup('cookie.group.test');
        $cookieGroup->snippetKeyDescription = 'cookie.group.test.description';
        $cookieGroup->setEntries(new CookieEntryCollection([$cookieGroupEntry]));

        /** @var StaticEntityRepository<SalesChannelAnalyticsCollection> $repository */
        $repository = new StaticEntityRepository([new SalesChannelAnalyticsCollection([])]);
        $systemConfigService = $this->createMock(SystemConfigService::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')
            ->willReturnCallback(function ($key) {
                return 'Translated: ' . $key;
            });

        $cookieGroupCollection = new CookieGroupCollection([$cookieGroup]);
        $cookieService = new CookieService($systemConfigService, $repository, $translator);
        $cookieService->translateCookieGroups($cookieGroupCollection);

        static::assertCount(1, $cookieGroupCollection);
        $group = $cookieGroupCollection->get('cookie.group.test');
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertSame('Translated: cookie.group.test', $group->snippetKeyName);
        static::assertSame('Translated: cookie.group.test.description', $group->snippetKeyDescription);
        $entries = $group->getEntries();
        static::assertNotNull($entries);
        static::assertCount(1, $entries);
        $entry = $entries->get('test-cookie');
        static::assertNotNull($entry);
        static::assertSame('Translated: cookie.entry.test', $entry->snippetKeyName);
        static::assertSame('Translated: cookie.entry.test.description', $entry->snippetKeyDescription);
        static::assertSame('test-cookie', $entry->cookie);
    }
}
