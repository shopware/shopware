<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Shopware\Storefront\Page\Robots\RobotsPage;
use Shopware\Storefront\Page\Robots\RobotsPageLoadedEvent;
use Shopware\Storefront\Page\Robots\RobotsPageLoader;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirective;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;
use Shopware\Storefront\Page\Robots\Struct\RobotsUserAgentBlock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(RobotsPageLoader::class)]
class RobotsPageLoaderTest extends TestCase
{
    private Stub&EventDispatcherInterface $eventDispatcher;

    /**
     * @var StaticEntityRepository<SalesChannelDomainCollection>
     */
    private StaticEntityRepository $salesChannelDomainRepository;

    private StaticSystemConfigService $systemConfigService;

    private RobotsPageLoader $robotsPageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = static::createStub(EventDispatcherInterface::class);
        $this->salesChannelDomainRepository = new StaticEntityRepository([]);
        $this->systemConfigService = new StaticSystemConfigService();

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser(new EventDispatcher())
        );
    }

    public function testLoadWithEmptyHostname(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        $this->assertBasicPageStructure($page, 0, 0, 0);
    }

    public function testLoadWithValidHostname(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $domain = $this->createDomain('https://example.com', $salesChannelId);
        $domains = [$domain];

        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => "Disallow: /account/\nDisallow: /checkout/\nDisallow: /widgets/\nAllow: /widgets/cms/\nAllow: /widgets/menu/offcanvas",
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        $this->assertBasicPageStructure($page, 1, 1, 0);
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());

        $domainRule = $page->getDomainRules()->first();
        static::assertInstanceOf(DomainRuleStruct::class, $domainRule);

        $directives = $domainRule->getDirectives();
        static::assertCount(5, $directives);
        static::assertSame(RobotsDirectiveType::DISALLOW, $directives[0]->type);
        static::assertSame('/account/', $directives[0]->value);
        static::assertSame(RobotsDirectiveType::DISALLOW, $directives[1]->type);
        static::assertSame('/checkout/', $directives[1]->value);
        static::assertSame(RobotsDirectiveType::DISALLOW, $directives[2]->type);
        static::assertSame('/widgets/', $directives[2]->value);
        static::assertSame(RobotsDirectiveType::ALLOW, $directives[3]->type);
        static::assertSame('/widgets/cms/', $directives[3]->value);
        static::assertSame(RobotsDirectiveType::ALLOW, $directives[4]->type);
        static::assertSame('/widgets/menu/offcanvas', $directives[4]->value);

        static::assertSame('', $domainRule->getBasePath());
    }

    public function testLoadWithMultipleDomains(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domains = [
            $this->createDomain('https://example.com', $salesChannelId1),
            $this->createDomain('https://example.com/en', $salesChannelId2),
        ];

        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => [
                "Disallow: /account/\nDisallow: /checkout/\nDisallow: /widgets/\nAllow: /widgets/cms/\nAllow: /widgets/menu/offcanvas",
                "Disallow: /private/\nDisallow: /admin/\nAllow: /widgets/cms/",
            ],
        ]);

        $page = $this->robotsPageLoader->load($request, $context);

        $this->assertBasicPageStructure($page, 2, 2, 0);
        static::assertEquals(
            ['https://example.com/sitemap.xml', 'https://example.com/en/sitemap.xml'],
            $page->getSitemaps()
        );

        $domainRules = $page->getDomainRules();
        $firstDomainRule = $domainRules->first();
        $secondDomainRule = $domainRules->last();

        static::assertNotNull($firstDomainRule);
        static::assertNotNull($secondDomainRule);

        static::assertInstanceOf(DomainRuleStruct::class, $firstDomainRule);
        static::assertSame('', $firstDomainRule->getBasePath());

        $firstDirectives = $firstDomainRule->getDirectives();
        static::assertCount(5, $firstDirectives);
        static::assertSame(RobotsDirectiveType::DISALLOW, $firstDirectives[0]->type);
        static::assertSame('/account/', $firstDirectives[0]->value);
        static::assertSame(RobotsDirectiveType::DISALLOW, $firstDirectives[1]->type);
        static::assertSame('/checkout/', $firstDirectives[1]->value);
        static::assertSame(RobotsDirectiveType::DISALLOW, $firstDirectives[2]->type);
        static::assertSame('/widgets/', $firstDirectives[2]->value);
        static::assertSame(RobotsDirectiveType::ALLOW, $firstDirectives[3]->type);
        static::assertSame('/widgets/cms/', $firstDirectives[3]->value);
        static::assertSame(RobotsDirectiveType::ALLOW, $firstDirectives[4]->type);
        static::assertSame('/widgets/menu/offcanvas', $firstDirectives[4]->value);

        static::assertInstanceOf(DomainRuleStruct::class, $secondDomainRule);
        static::assertSame('/en', $secondDomainRule->getBasePath());

        $secondDirectives = $secondDomainRule->getDirectives();
        static::assertCount(3, $secondDirectives);
        static::assertSame(RobotsDirectiveType::DISALLOW, $secondDirectives[0]->type);
        static::assertSame('/en/private/', $secondDirectives[0]->value);
        static::assertSame(RobotsDirectiveType::DISALLOW, $secondDirectives[1]->type);
        static::assertSame('/en/admin/', $secondDirectives[1]->value);
        static::assertSame(RobotsDirectiveType::ALLOW, $secondDirectives[2]->type);
        static::assertSame('/en/widgets/cms/', $secondDirectives[2]->value);
    }

    public function testLoadWithEmptyOrMissingRobotsRules(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $domain = $this->createDomain('https://example.com', $salesChannelId);
        $domains = [$domain];

        // Expect event to be dispatched twice (once per load call)
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->exactly(2))
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        // Test with empty string robots rules
        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => '',
        ], $eventDispatcher);

        $page = $this->robotsPageLoader->load($request, $context);

        $this->assertBasicPageStructure($page, 1, 0, 0);
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());

        // Test with no robots rules configured at all
        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [], $eventDispatcher);

        $page = $this->robotsPageLoader->load($request, $context);

        $this->assertBasicPageStructure($page, 1, 0, 0);
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());
    }

    public function testLoadWithHttpAndHttpsDomains(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $httpDomain = $this->createDomain('http://example.com', $salesChannelId);
        $httpsDomain = $this->createDomain('https://example.com', $salesChannelId);
        $domains = [$httpDomain, $httpsDomain];

        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => "Disallow: /account/\nAllow: /public/",
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        // HTTP and HTTPS domains for same hostname should be deduplicated
        // Both should have domainHostname = '' and should be treated as the same
        $this->assertBasicPageStructure($page, 1, 1, 0);
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());

        $domainRule = $page->getDomainRules()->first();
        static::assertInstanceOf(DomainRuleStruct::class, $domainRule);

        $directives = $domainRule->getDirectives();
        static::assertCount(2, $directives);
        static::assertSame(RobotsDirectiveType::DISALLOW, $directives[0]->type);
        static::assertSame('/account/', $directives[0]->value);
        static::assertSame(RobotsDirectiveType::ALLOW, $directives[1]->type);
        static::assertSame('/public/', $directives[1]->value);

        static::assertSame('', $domainRule->getBasePath());
    }

    public function testLoadWithDifferentHostnames(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        // Domain for example.com
        $domain1 = $this->createDomain('https://example.com', $salesChannelId1);

        // Domain for different.org (different hostname)
        $domain2 = $this->createDomain('https://different.org', $salesChannelId2);

        $domains = [$domain1, $domain2];

        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => [
                "Disallow: /account/\nAllow: /public/",
                "Disallow: /private/\nAllow: /api/",
            ],
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        // Should only find the domain matching the hostname (example.com)
        $this->assertBasicPageStructure($page, 1, 1, 0);
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());

        $domainRule = $page->getDomainRules()->first();
        static::assertInstanceOf(DomainRuleStruct::class, $domainRule);

        $directives = $domainRule->getDirectives();
        static::assertCount(2, $directives);
        static::assertSame(RobotsDirectiveType::DISALLOW, $directives[0]->type);
        static::assertSame('/account/', $directives[0]->value);
        static::assertSame(RobotsDirectiveType::ALLOW, $directives[1]->type);
        static::assertSame('/public/', $directives[1]->value);

        static::assertSame('', $domainRule->getBasePath());
    }

    /**
     * @param list<string> $domainUrls
     */
    #[DataProvider('selectsDomainMatchingTheRequestedHostProvider')]
    #[TestDox('selects the domain for the requested host: $_dataName')]
    public function testSelectsDomainMatchingTheRequestedHost(string $httpHost, array $domainUrls, string $expectedSitemap, string $expectedDirective): void
    {
        $request = new Request(server: ['HTTP_HOST' => $httpHost]);

        // Every domain belongs to its own sales channel with its own rule
        $domains = [];
        $rules = [];
        foreach ($domainUrls as $index => $url) {
            $domains[] = $this->createDomain($url, 'sales-channel-' . $index);
            $rules[] = 'Disallow: /sales-channel-' . $index . '/';
        }

        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => $rules,
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, Context::createDefaultContext());

        static::assertEquals([$expectedSitemap], $page->getSitemaps());

        $domainRule = $page->getDomainRules()->first();
        static::assertInstanceOf(DomainRuleStruct::class, $domainRule);
        static::assertCount(1, $domainRule->getDirectives());
        static::assertSame($expectedDirective, $domainRule->getDirectives()[0]->value);
        static::assertSame('', $domainRule->getBasePath());
    }

    public static function selectsDomainMatchingTheRequestedHostProvider(): iterable
    {
        yield 'exact host wins over a subdomain of another sales channel (#17735)' => [
            'httpHost' => 'example.com',
            'domainUrls' => ['https://www.example.com', 'https://example.com'],
            'expectedSitemap' => 'https://example.com/sitemap.xml',
            'expectedDirective' => '/sales-channel-1/',
        ];

        yield 'parent host of another sales channel is never selected (#17735)' => [
            'httpHost' => 'www.example.com',
            'domainUrls' => ['https://example.com', 'https://www.example.com'],
            'expectedSitemap' => 'https://www.example.com/sitemap.xml',
            'expectedDirective' => '/sales-channel-1/',
        ];

        yield 'host comparison ignores letter case and keeps the port' => [
            'httpHost' => 'Example.COM:8000',
            'domainUrls' => ['https://www.example.com', 'https://EXAMPLE.com:8000'],
            'expectedSitemap' => 'https://EXAMPLE.com:8000/sitemap.xml',
            'expectedDirective' => '/sales-channel-1/',
        ];

        yield 'port in the host header only matches a domain on that exact port' => [
            'httpHost' => 'shop.test:80',
            'domainUrls' => ['http://shop.test:8000', 'http://shop.test:8080', 'http://shop.test:80'],
            'expectedSitemap' => 'http://shop.test:80/sitemap.xml',
            'expectedDirective' => '/sales-channel-2/',
        ];

        yield 'explicit port 80 in the host header matches the http domain without a port' => [
            'httpHost' => 'shop.test:80',
            'domainUrls' => ['http://shop.test:8000', 'http://shop.test'],
            'expectedSitemap' => 'http://shop.test/sitemap.xml',
            'expectedDirective' => '/sales-channel-1/',
        ];

        yield 'host header without a port still matches a domain with a port' => [
            'httpHost' => 'shop.test',
            'domainUrls' => ['http://www.shop.test', 'http://shop.test:8000'],
            'expectedSitemap' => 'http://shop.test:8000/sitemap.xml',
            'expectedDirective' => '/sales-channel-1/',
        ];

        yield 'explicit https port 443 in the host header matches the https domain' => [
            'httpHost' => 'example.com:443',
            'domainUrls' => ['https://www.example.com', 'https://example.com'],
            'expectedSitemap' => 'https://example.com/sitemap.xml',
            'expectedDirective' => '/sales-channel-1/',
        ];

        yield 'https domain listed first is not replaced by the http one' => [
            'httpHost' => 'example.com',
            'domainUrls' => ['https://example.com', 'http://example.com'],
            'expectedSitemap' => 'https://example.com/sitemap.xml',
            'expectedDirective' => '/sales-channel-0/',
        ];

        yield 'subdomain fallback prefers the https domain over the http one' => [
            'httpHost' => 'example.com',
            'domainUrls' => ['http://www.example.com', 'https://www.example.com'],
            'expectedSitemap' => 'https://www.example.com/sitemap.xml',
            'expectedDirective' => '/sales-channel-1/',
        ];

        yield 'bare host falls back to its subdomain when nothing matches exactly' => [
            'httpHost' => 'example.com',
            'domainUrls' => ['https://www.example.com', 'https://different.org'],
            'expectedSitemap' => 'https://www.example.com/sitemap.xml',
            'expectedDirective' => '/sales-channel-0/',
        ];

        yield 'fallback ignores hosts that only end with the requested host' => [
            'httpHost' => 'example.com',
            'domainUrls' => ['https://myexample.com', 'https://www.example.com'],
            'expectedSitemap' => 'https://www.example.com/sitemap.xml',
            'expectedDirective' => '/sales-channel-1/',
        ];
    }

    public function testFallbackKeepsEveryPathVariantOfTheSubdomain(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);

        $this->robotsPageLoader = $this->setupLoaderWithDomains([
            $this->createDomain('https://www.example.com/de', 'sales-channel-de'),
            $this->createDomain('https://www.example.com/us', 'sales-channel-us'),
        ], [
            'core.basicInformation.robotsRules' => [
                'Disallow: /checkout/',
                'Disallow: /account/',
            ],
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, Context::createDefaultContext());

        static::assertEquals(
            ['https://www.example.com/de/sitemap.xml', 'https://www.example.com/us/sitemap.xml'],
            $page->getSitemaps()
        );

        $domainRules = $page->getDomainRules();
        static::assertCount(2, $domainRules);

        $germanRule = $domainRules->first();
        static::assertInstanceOf(DomainRuleStruct::class, $germanRule);
        static::assertSame('/de', $germanRule->getBasePath());
        static::assertSame('/de/checkout/', $germanRule->getDirectives()[0]->value);

        $usRule = $domainRules->last();
        static::assertInstanceOf(DomainRuleStruct::class, $usRule);
        static::assertSame('/us', $usRule->getBasePath());
        static::assertSame('/us/account/', $usRule->getDirectives()[0]->value);
    }

    public function testSearchesDomainsByTheBareRequestHost(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com:443']);

        $this->salesChannelDomainRepository = StaticEntityRepository::of(SalesChannelDomainCollection::class, [
            static function (Criteria $criteria): SalesChannelDomainCollection {
                static::assertEquals([
                    new ContainsFilter('url', 'example.com'),
                    new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT),
                ], $criteria->getFilters());

                return new SalesChannelDomainCollection();
            },
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, Context::createDefaultContext());

        $this->assertBasicPageStructure($page, 0, 0, 0);
    }

    public function testSelectsNoDomainWhenNoHostMatchesOrIsASubdomain(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);

        // `getDomains()` returns this for `example.com`, but it is a different shop
        $this->robotsPageLoader = $this->setupLoaderWithDomains([
            $this->createDomain('https://myexample.com'),
        ], [
            'core.basicInformation.robotsRules' => 'Disallow: /unrelated-sales-channel/',
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, Context::createDefaultContext());

        $this->assertBasicPageStructure($page, 0, 0, 0);
    }

    public function testLoadWithGlobalUserAgentBlocks(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domains = [
            $this->createDomain('https://example.com', $salesChannelId1),
            $this->createDomain('https://example.com/en', $salesChannelId2),
        ];

        // Configure robots rules with User-agent blocks for both sales channels
        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => [
                "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /account/\nAllow: /widgets/",
                "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /private/\nAllow: /api/",
            ],
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        // Should have sitemaps for both domains
        $this->assertBasicPageStructure($page, 2, 2, 1);
        static::assertEquals(
            ['https://example.com/sitemap.xml', 'https://example.com/en/sitemap.xml'],
            $page->getSitemaps()
        );

        $globalBlock = $page->getGlobalUserAgentBlocks()[0];
        static::assertSame('Googlebot', $globalBlock->userAgent);

        // The global block should contain both non-path directives (Crawl-delay) and path directives
        $directives = $globalBlock->directives;
        static::assertCount(5, $directives); // We expect 5 directives: 1 Crawl-delay + 4 path directives

        // Check that Crawl-delay (non-path directive) is present
        $crawlDelayDirectives = array_filter($directives, static fn ($d) => $d->type === RobotsDirectiveType::CRAWL_DELAY);
        static::assertCount(1, $crawlDelayDirectives);

        // Check that both domain's path directives are present with correct paths
        $this->assertDirectivePaths($directives, RobotsDirectiveType::DISALLOW, ['/account/', '/en/private/']);
        $this->assertDirectivePaths($directives, RobotsDirectiveType::ALLOW, ['/en/api/', '/widgets/']);

        // Domain rules should still exist for both domains but user-agent path directives
        // must be rendered only inside the global user-agent block, not duplicated here.
        $domainRules = $page->getDomainRules();
        $firstDomainRule = $domainRules->first();
        $secondDomainRule = $domainRules->last();

        static::assertNotNull($firstDomainRule);
        static::assertNotNull($secondDomainRule);

        static::assertSame('', $firstDomainRule->getBasePath());
        static::assertSame('/en', $secondDomainRule->getBasePath());

        static::assertCount(0, $firstDomainRule->getDirectives());
        static::assertCount(0, $secondDomainRule->getDirectives());
    }

    public function testLoadWithUserAgentBlocksOnlyNonPathDirectives(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domains = [
            $this->createDomain('https://example.com', $salesChannelId1),
            $this->createDomain('https://example.com/en', $salesChannelId2),
        ];

        // Configure robots rules with User-agent blocks that have only non-path directives
        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => [
                "User-agent: Googlebot\nCrawl-delay: 10\nRequest-rate: 1/10",
                "User-agent: Googlebot\nCrawl-delay: 10\nVisit-time: 0600-1200",
            ],
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        // Should have sitemaps for both domains
        static::assertCount(2, $page->getSitemaps());

        // Should have two global User-agent blocks (different non-path directives)
        $globalBlocks = $page->getGlobalUserAgentBlocks();
        static::assertCount(2, $globalBlocks);

        $this->assertUserAgentBlocksHaveCorrectDirectiveTypes($globalBlocks);

        // Domain rules should exist for both domains (they contain the original parsed rules)
        static::assertCount(2, $page->getDomainRules());
    }

    public function testLoadWithUserAgentBlocksOnlyPathDirectives(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domains = [
            $this->createDomain('https://example.com', $salesChannelId1),
            $this->createDomain('https://example.com/en', $salesChannelId2),
        ];

        // Configure robots rules with User-agent blocks that have only path directives
        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => [
                "User-agent: Googlebot\nDisallow: /account/\nAllow: /widgets/",
                "User-agent: Googlebot\nDisallow: /private/\nAllow: /api/",
            ],
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        // Should have sitemaps for both domains
        static::assertCount(2, $page->getSitemaps());

        // Should have one global User-agent block (deduplicated)
        $globalBlocks = $page->getGlobalUserAgentBlocks();
        static::assertCount(1, $globalBlocks);

        $globalBlock = $globalBlocks[0];
        static::assertSame('Googlebot', $globalBlock->userAgent);

        // Should have only path directives (no non-path directives)
        $directives = $globalBlock->directives;
        static::assertCount(4, $directives);

        // Verify the paths are correctly prefixed
        $this->assertDirectivePaths($directives, RobotsDirectiveType::DISALLOW, ['/account/', '/en/private/']);
        $this->assertDirectivePaths($directives, RobotsDirectiveType::ALLOW, ['/en/api/', '/widgets/']);

        // Domain rules should also exist with the same path directives
        static::assertCount(2, $page->getDomainRules());
    }

    public function testLoadWithMultipleDifferentUserAgentBlocks(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domains = [
            $this->createDomain('https://example.com', $salesChannelId1),
            $this->createDomain('https://example.com/en', $salesChannelId2),
        ];

        // Configure robots rules with different User-agent blocks
        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => [
                "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /account/\n\nUser-agent: Bingbot\nDisallow: /admin/",
                "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /private/\n\nUser-agent: Bingbot\nDisallow: /secret/",
            ],
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        // Should have sitemaps for both domains
        static::assertCount(2, $page->getSitemaps());

        // Should have two global User-agent blocks (different user agents)
        $globalBlocks = $page->getGlobalUserAgentBlocks();
        static::assertCount(2, $globalBlocks);

        // Sort by user agent for consistent testing
        usort($globalBlocks, static fn ($a, $b) => strcmp($a->userAgent, $b->userAgent));

        $bingbotBlock = $globalBlocks[0];
        $googlebotBlock = $globalBlocks[1];

        static::assertSame('Bingbot', $bingbotBlock->userAgent);
        static::assertSame('Googlebot', $googlebotBlock->userAgent);

        // Googlebot block should have merged directives from both domains
        static::assertCount(3, $googlebotBlock->directives); // 1 Crawl-delay + 2 Disallow
        $this->assertDirectivePaths($googlebotBlock->directives, RobotsDirectiveType::DISALLOW, ['/account/', '/en/private/']);

        // Bingbot block should have merged directives from both domains
        static::assertCount(2, $bingbotBlock->directives); // 2 Disallow
        $this->assertDirectivePaths($bingbotBlock->directives, RobotsDirectiveType::DISALLOW, ['/admin/', '/en/secret/']);

        // Domain rules should exist for both domains
        static::assertCount(2, $page->getDomainRules());
    }

    private function createDomain(string $url, string $salesChannelId = 'test-sales-channel-id'): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id-' . md5($url));
        $domain->setUrl($url);
        $domain->setSalesChannelId($salesChannelId);

        return $domain;
    }

    /**
     * Sets up the RobotsPageLoader with given domains and optional config
     *
     * @param SalesChannelDomainEntity[] $domains
     * @param array<string, string|array<int, string>> $config
     */
    private function setupLoaderWithDomains(array $domains, array $config = [], ?EventDispatcherInterface $eventDispatcher = null): RobotsPageLoader
    {
        $this->salesChannelDomainRepository = new StaticEntityRepository([
            new SalesChannelDomainCollection($domains),
        ]);

        foreach ($config as $key => $value) {
            if (\is_array($value)) {
                // Handle array config (for multiple sales channels)
                foreach ($value as $index => $configValue) {
                    if (isset($domains[$index]) && $domains[$index] instanceof SalesChannelDomainEntity) {
                        $this->systemConfigService->set($key, $configValue, $domains[$index]->getSalesChannelId());
                    }
                }
            } else {
                $this->systemConfigService->set($key, $value);
            }
        }

        return new RobotsPageLoader(
            $eventDispatcher ?? $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser(new EventDispatcher())
        );
    }

    /**
     * Common assertions for basic page structure
     */
    private function assertBasicPageStructure(
        RobotsPage $page,
        int $expectedSitemaps,
        int $expectedDomainRules,
        int $expectedGlobalBlocks
    ): void {
        static::assertCount($expectedSitemaps, $page->getSitemaps());
        static::assertCount($expectedDomainRules, $page->getDomainRules());
        static::assertCount($expectedGlobalBlocks, $page->getGlobalUserAgentBlocks());
    }

    /**
     * Common setup for tests that need event dispatcher expectations.
     *
     * Rebuilds the loader with a mock dispatcher (kept separate from the
     * stub property) so the expectation is verified against the instance the
     * loader actually dispatches through.
     */
    private function setupEventDispatcherExpectation(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $this->robotsPageLoader = new RobotsPageLoader(
            $eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser(new EventDispatcher())
        );
    }

    /**
     * Helper to assert that User-agent blocks have correct directive types
     *
     * @param array<RobotsUserAgentBlock> $globalBlocks
     */
    private function assertUserAgentBlocksHaveCorrectDirectiveTypes(array $globalBlocks): void
    {
        // Sort by directive count for consistent testing (both should have 2 directives)
        usort($globalBlocks, static fn ($a, $b) => \count($a->directives) <=> \count($b->directives));

        $firstBlock = $globalBlocks[0];
        $secondBlock = $globalBlocks[1];

        static::assertSame('Googlebot', $firstBlock->userAgent);
        static::assertSame('Googlebot', $secondBlock->userAgent);

        // Each block should have 2 directives (1 Crawl-delay + 1 other directive)
        static::assertCount(2, $firstBlock->directives);
        static::assertCount(2, $secondBlock->directives);

        // Collect all directive types from both blocks
        $allDirectiveTypes = $this->collectDirectiveTypes($globalBlocks);

        static::assertEquals([
            RobotsDirectiveType::CRAWL_DELAY,
            RobotsDirectiveType::CRAWL_DELAY,
            RobotsDirectiveType::REQUEST_RATE,
            RobotsDirectiveType::VISIT_TIME,
        ], $allDirectiveTypes);
    }

    /**
     * Collects and sorts all directive types from given blocks
     *
     * @param array<RobotsUserAgentBlock> $blocks
     *
     * @return list<RobotsDirectiveType>
     */
    private function collectDirectiveTypes(array $blocks): array
    {
        $allDirectiveTypes = [];
        foreach ($blocks as $block) {
            $types = array_map(static fn ($d) => $d->type, $block->directives);
            $allDirectiveTypes = array_merge($allDirectiveTypes, $types);
        }

        // Sort by enum value for consistent ordering
        usort($allDirectiveTypes, static fn ($a, $b) => $a->value <=> $b->value);

        return $allDirectiveTypes;
    }

    /**
     * Asserts that directives contain specific paths for a given directive type
     *
     * @param array<RobotsDirective> $directives
     * @param list<string> $expectedPaths
     */
    private function assertDirectivePaths(array $directives, RobotsDirectiveType $type, array $expectedPaths): void
    {
        $filteredDirectives = array_filter($directives, static fn ($d) => $d->type === $type);
        $actualPaths = array_map(static fn ($d) => $d->value, array_values($filteredDirectives));
        sort($actualPaths);
        sort($expectedPaths);

        static::assertEquals($expectedPaths, $actualPaths);
    }
}
