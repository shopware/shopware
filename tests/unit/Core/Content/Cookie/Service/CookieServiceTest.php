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
    public function testFilterCookieGroupsWithNoAnalytics(): void
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
        $result = $cookieService->filterCookieGroups($salesChannelContext, $cookieGroups);

        // Should remove Google Analytics cookies but keep other groups
        static::assertCount(1, $result);
        static::assertSame('cookie.groupOther', $result[0]['snippet_name']);
    }

    public function testConvertToCookieGroupCollection(): void
    {
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
        $result = $cookieService->convertToCookieGroupCollection($cookieGroups);

        static::assertCount(1, $result);
        $group = $result->first();
        static::assertInstanceOf(CookieGroup::class, $group);
        static::assertTrue($group->isRequired);
        static::assertCount(1, $group->entries);
        static::assertSame('test.group', $group->snippetName);
        static::assertSame('Test Group Description', $group->snippetDescription);
    }

    public function testTranslateCookieGroups(): void
    {
        $salesChannelContext = Generator::generateSalesChannelContext();

        $cookieGroups = [
            [
                'snippet_name' => 'cookie.group.test',
                'snippet_description' => 'cookie.group.test.description',
                'entries' => [
                    [
                        'snippet_name' => 'cookie.entry.test',
                        'snippet_description' => 'cookie.entry.test.description',
                        'cookie' => 'test-cookie',
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
        $result = $cookieService->translateCookieGroups($cookieGroups, $salesChannelContext);

        static::assertCount(1, $result);
        static::assertSame('Translated: cookie.group.test', $result[0]['snippet_name']);
        static::assertSame('Translated: cookie.group.test.description', $result[0]['snippet_description']);
        static::assertSame('Translated: cookie.entry.test', $result[0]['entries'][0]['snippet_name']);
        static::assertSame('Translated: cookie.entry.test.description', $result[0]['entries'][0]['snippet_description']);
    }
}
