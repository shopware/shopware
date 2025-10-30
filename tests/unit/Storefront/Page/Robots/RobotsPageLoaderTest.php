<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Shopware\Storefront\Page\Robots\RobotsPage;
use Shopware\Storefront\Page\Robots\RobotsPageLoadedEvent;
use Shopware\Storefront\Page\Robots\RobotsPageLoader;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct;
use Shopware\Storefront\Page\Robots\Struct\RobotsDirectiveType;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(RobotsPageLoader::class)]
class RobotsPageLoaderTest extends TestCase
{
    private MockObject&EventDispatcherInterface $eventDispatcher;

    /**
     * @var StaticEntityRepository<SalesChannelDomainCollection>
     */
    private StaticEntityRepository $salesChannelDomainRepository;

    private StaticSystemConfigService $systemConfigService;

    private RobotsPageLoader $robotsPageLoader;

    protected function setUp(): void
    {
        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
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

        $domain = $this->createExampleComDomain($salesChannelId);
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

        $domains = $this->createStandardDomains($salesChannelId1, $salesChannelId2);

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

    public function testLoadWithEmptyRobotsRules(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $domain = $this->createExampleComDomain($salesChannelId);
        $domains = [$domain];

        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains, [
            'core.basicInformation.robotsRules' => '',
        ]);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        $this->assertBasicPageStructure($page, 1, 0, 0); // Empty rules should not create domain rule
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());
    }

    public function testLoadWithHttpAndHttpsDomains(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $httpDomain = $this->createExampleComHttpDomain($salesChannelId);
        $httpsDomain = $this->createExampleComDomain($salesChannelId);
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

    public function testLoadWithDomainHavingNoRobotsRules(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $domain = $this->createExampleComDomain($salesChannelId);
        $domains = [$domain];

        // Don't set any robots rules for this sales channel
        $this->robotsPageLoader = $this->setupLoaderWithDomains($domains);

        $this->setupEventDispatcherExpectation();

        $page = $this->robotsPageLoader->load($request, $context);

        $this->assertBasicPageStructure($page, 1, 0, 0); // No rules should be present
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());
    }

    public function testLoadWithDifferentHostnames(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        // Domain for example.com
        $domain1 = $this->createExampleComDomain($salesChannelId1);

        // Domain for different.org (different hostname)
        $domain2 = $this->createDifferentOrgDomain($salesChannelId2);

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

    public function testLoadWithGlobalUserAgentBlocks(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domains = $this->createStandardDomains($salesChannelId1, $salesChannelId2);

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
        $crawlDelayDirectives = array_filter($directives, fn ($d) => $d->type === RobotsDirectiveType::CRAWL_DELAY);
        static::assertCount(1, $crawlDelayDirectives);

        // Check that both domain's path directives are present with correct paths
        $disallowDirectives = array_filter($directives, fn ($d) => $d->type === RobotsDirectiveType::DISALLOW);
        $allowDirectives = array_filter($directives, fn ($d) => $d->type === RobotsDirectiveType::ALLOW);

        static::assertCount(2, $disallowDirectives);
        static::assertCount(2, $allowDirectives);

        // Verify the paths are correctly prefixed
        $disallowPaths = array_map(fn ($d) => $d->value, array_values($disallowDirectives));
        $allowPaths = array_map(fn ($d) => $d->value, array_values($allowDirectives));

        sort($disallowPaths);
        sort($allowPaths);

        static::assertEquals(['/account/', '/en/private/'], $disallowPaths);
        static::assertEquals(['/en/api/', '/widgets/'], $allowPaths);

        // Domain rules should still exist but only contain the path directives for each domain
        $domainRules = $page->getDomainRules();
        $firstDomainRule = $domainRules->first();
        $secondDomainRule = $domainRules->last();

        static::assertNotNull($firstDomainRule);
        static::assertNotNull($secondDomainRule);

        static::assertSame('', $firstDomainRule->getBasePath());
        static::assertSame('/en', $secondDomainRule->getBasePath());

        static::assertCount(2, $firstDomainRule->getDirectives());
        static::assertCount(2, $secondDomainRule->getDirectives());
    }

    public function testLoadWithUserAgentBlocksOnlyNonPathDirectives(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domain1 = new SalesChannelDomainEntity();
        $domain1->setId('test-domain-id-1');
        $domain1->setUrl('https://example.com');
        $domain1->setSalesChannelId($salesChannelId1);

        $domain2 = new SalesChannelDomainEntity();
        $domain2->setId('test-domain-id-2');
        $domain2->setUrl('https://example.com/en');
        $domain2->setSalesChannelId($salesChannelId2);

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$domain1, $domain2])]);

        // Configure robots rules with User-agent blocks that have only non-path directives
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "User-agent: Googlebot\nCrawl-delay: 10\nRequest-rate: 1/10",
            $salesChannelId1
        );
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "User-agent: Googlebot\nCrawl-delay: 10\nVisit-time: 0600-1200",
            $salesChannelId2
        );

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser(new EventDispatcher())
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        // Should have sitemaps for both domains
        static::assertCount(2, $page->getSitemaps());

        // Should have two global User-agent blocks (different non-path directives)
        static::assertCount(2, $page->getGlobalUserAgentBlocks());

        $globalBlocks = $page->getGlobalUserAgentBlocks();

        // Sort by directive count for consistent testing (both should have 2 directives)
        usort($globalBlocks, fn ($a, $b) => \count($a->directives) <=> \count($b->directives));

        $firstBlock = $globalBlocks[0];
        $secondBlock = $globalBlocks[1];

        static::assertSame('Googlebot', $firstBlock->userAgent);
        static::assertSame('Googlebot', $secondBlock->userAgent);

        // Each block should have 2 directives (1 Crawl-delay + 1 other directive)
        static::assertCount(2, $firstBlock->directives);
        static::assertCount(2, $secondBlock->directives);

        // Collect all directive types from both blocks
        $allDirectiveTypes = [];
        foreach ($globalBlocks as $block) {
            $types = array_map(fn ($d) => $d->type, $block->directives);
            $allDirectiveTypes = array_merge($allDirectiveTypes, $types);
        }

        // Sort by enum value for consistent ordering
        usort($allDirectiveTypes, fn ($a, $b) => $a->value <=> $b->value);

        static::assertEquals([
            RobotsDirectiveType::CRAWL_DELAY,
            RobotsDirectiveType::CRAWL_DELAY,
            RobotsDirectiveType::REQUEST_RATE,
            RobotsDirectiveType::VISIT_TIME,
        ], $allDirectiveTypes);

        // Domain rules should exist for both domains (they contain the original parsed rules)
        static::assertCount(2, $page->getDomainRules());
    }

    public function testLoadWithUserAgentBlocksOnlyPathDirectives(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domain1 = new SalesChannelDomainEntity();
        $domain1->setId('test-domain-id-1');
        $domain1->setUrl('https://example.com');
        $domain1->setSalesChannelId($salesChannelId1);

        $domain2 = new SalesChannelDomainEntity();
        $domain2->setId('test-domain-id-2');
        $domain2->setUrl('https://example.com/en');
        $domain2->setSalesChannelId($salesChannelId2);

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$domain1, $domain2])]);

        // Configure robots rules with User-agent blocks that have only path directives
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "User-agent: Googlebot\nDisallow: /account/\nAllow: /widgets/",
            $salesChannelId1
        );
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "User-agent: Googlebot\nDisallow: /private/\nAllow: /api/",
            $salesChannelId2
        );

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser(new EventDispatcher())
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        // Should have sitemaps for both domains
        static::assertCount(2, $page->getSitemaps());

        // Should have one global User-agent block (deduplicated)
        static::assertCount(1, $page->getGlobalUserAgentBlocks());

        $globalBlock = $page->getGlobalUserAgentBlocks()[0];
        static::assertSame('Googlebot', $globalBlock->userAgent);

        // Should have only path directives (no non-path directives)
        $directives = $globalBlock->directives;
        static::assertCount(4, $directives);

        $disallowDirectives = array_filter($directives, fn ($d) => $d->type === RobotsDirectiveType::DISALLOW);
        $allowDirectives = array_filter($directives, fn ($d) => $d->type === RobotsDirectiveType::ALLOW);

        static::assertCount(2, $disallowDirectives);
        static::assertCount(2, $allowDirectives);

        // Verify the paths are correctly prefixed
        $disallowPaths = array_map(fn ($d) => $d->value, array_values($disallowDirectives));
        $allowPaths = array_map(fn ($d) => $d->value, array_values($allowDirectives));

        sort($disallowPaths);
        sort($allowPaths);

        static::assertEquals(['/account/', '/en/private/'], $disallowPaths);
        static::assertEquals(['/en/api/', '/widgets/'], $allowPaths);

        // Domain rules should also exist with the same path directives
        static::assertCount(2, $page->getDomainRules());
    }

    public function testLoadWithMultipleDifferentUserAgentBlocks(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        $domain1 = new SalesChannelDomainEntity();
        $domain1->setId('test-domain-id-1');
        $domain1->setUrl('https://example.com');
        $domain1->setSalesChannelId($salesChannelId1);

        $domain2 = new SalesChannelDomainEntity();
        $domain2->setId('test-domain-id-2');
        $domain2->setUrl('https://example.com/en');
        $domain2->setSalesChannelId($salesChannelId2);

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$domain1, $domain2])]);

        // Configure robots rules with different User-agent blocks
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /account/\n\nUser-agent: Bingbot\nDisallow: /admin/",
            $salesChannelId1
        );
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "User-agent: Googlebot\nCrawl-delay: 10\nDisallow: /private/\n\nUser-agent: Bingbot\nDisallow: /secret/",
            $salesChannelId2
        );

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser(new EventDispatcher())
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        // Should have sitemaps for both domains
        static::assertCount(2, $page->getSitemaps());

        // Should have two global User-agent blocks (different user agents)
        static::assertCount(2, $page->getGlobalUserAgentBlocks());

        $globalBlocks = $page->getGlobalUserAgentBlocks();

        // Sort by user agent for consistent testing
        usort($globalBlocks, fn ($a, $b) => strcmp($a->userAgent, $b->userAgent));

        $bingbotBlock = $globalBlocks[0];
        $googlebotBlock = $globalBlocks[1];

        static::assertSame('Bingbot', $bingbotBlock->userAgent);
        static::assertSame('Googlebot', $googlebotBlock->userAgent);

        // Googlebot block should have merged directives from both domains
        $googlebotDirectives = $googlebotBlock->directives;
        static::assertCount(3, $googlebotDirectives); // 1 Crawl-delay + 2 Disallow

        $googlebotDisallowPaths = array_map(
            fn ($d) => $d->value,
            array_filter($googlebotDirectives, fn ($d) => $d->type === RobotsDirectiveType::DISALLOW)
        );
        sort($googlebotDisallowPaths);
        static::assertEquals(['/account/', '/en/private/'], $googlebotDisallowPaths);

        // Bingbot block should have merged directives from both domains
        $bingbotDirectives = $bingbotBlock->directives;
        static::assertCount(2, $bingbotDirectives); // 2 Disallow

        $bingbotDisallowPaths = array_map(
            fn ($d) => $d->value,
            array_filter($bingbotDirectives, fn ($d) => $d->type === RobotsDirectiveType::DISALLOW)
        );
        sort($bingbotDisallowPaths);
        static::assertEquals(['/admin/', '/en/secret/'], $bingbotDisallowPaths);

        // Domain rules should exist for both domains
        static::assertCount(2, $page->getDomainRules());
    }

    /**
     * Creates a standard test domain for example.com
     */
    private function createExampleComDomain(string $salesChannelId = 'test-sales-channel-id'): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id');
        $domain->setUrl('https://example.com');
        $domain->setSalesChannelId($salesChannelId);

        return $domain;
    }

    /**
     * Creates a standard test domain for example.com/en
     */
    private function createExampleComEnDomain(string $salesChannelId = 'test-sales-channel-id'): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id-en');
        $domain->setUrl('https://example.com/en');
        $domain->setSalesChannelId($salesChannelId);

        return $domain;
    }

    /**
     * Creates a standard test domain for example.com with HTTP
     */
    private function createExampleComHttpDomain(string $salesChannelId = 'test-sales-channel-id'): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id-http');
        $domain->setUrl('http://example.com');
        $domain->setSalesChannelId($salesChannelId);

        return $domain;
    }

    /**
     * Creates a standard test domain for different.org
     */
    private function createDifferentOrgDomain(string $salesChannelId = 'test-sales-channel-id'): SalesChannelDomainEntity
    {
        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id-different');
        $domain->setUrl('https://different.org');
        $domain->setSalesChannelId($salesChannelId);

        return $domain;
    }

    /**
     * Creates standard two-domain setup for example.com and example.com/en
     *
     * @return SalesChannelDomainEntity[]
     */
    private function createStandardDomains(string $salesChannelId1 = 'test-sales-channel-id-1', string $salesChannelId2 = 'test-sales-channel-id-2'): array
    {
        return [
            $this->createExampleComDomain($salesChannelId1),
            $this->createExampleComEnDomain($salesChannelId2),
        ];
    }

    /**
     * Sets up the RobotsPageLoader with given domains and optional config
     *
     * @param SalesChannelDomainEntity[] $domains
     * @param array<string, string|array<int, string>> $config
     */
    private function setupLoaderWithDomains(array $domains, array $config = []): RobotsPageLoader
    {
        $this->salesChannelDomainRepository = new StaticEntityRepository([
            new SalesChannelDomainCollection($domains),
        ]);

        foreach ($config as $key => $value) {
            if (\is_array($value)) {
                // Handle array config (for multiple sales channels)
                foreach ($value as $index => $configValue) {
                    if (isset($domains[$index]) && $domains[$index] instanceof SalesChannelDomainEntity) {
                        $this->systemConfigService->set($key, $configValue, $domains[$index]->salesChannelId);
                    }
                }
            } else {
                $this->systemConfigService->set($key, $value);
            }
        }

        return new RobotsPageLoader(
            $this->eventDispatcher,
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
     * Common setup for tests that need event dispatcher expectations
     */
    private function setupEventDispatcherExpectation(): void
    {
        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));
    }
}
