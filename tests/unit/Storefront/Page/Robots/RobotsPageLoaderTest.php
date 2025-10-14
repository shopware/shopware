<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Page\Robots;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\ContainsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Shopware\Core\Test\Stub\SystemConfigService\StaticSystemConfigService;
use Shopware\Storefront\Page\Robots\Parser\RobotsDirectiveParser;
use Shopware\Storefront\Page\Robots\RobotsPageLoadedEvent;
use Shopware\Storefront\Page\Robots\RobotsPageLoader;
use Shopware\Storefront\Page\Robots\Struct\DomainRuleStruct;
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
            new RobotsDirectiveParser()
        );
    }

    public function testLoadWithEmptyHostname(): void
    {
        $request = new Request();
        $context = Context::createDefaultContext();

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        static::assertEmpty($page->getSitemaps());
        static::assertCount(0, $page->getDomainRules());
    }

    public function testLoadWithValidHostname(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id');
        $domain->setUrl('https://example.com');
        $domain->setSalesChannelId($salesChannelId);

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('url', 'example.com'));
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$domain])]);

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser()
        );

        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "Disallow: /account/\nDisallow: /checkout/\nDisallow: /widgets/\nAllow: /widgets/cms/\nAllow: /widgets/menu/offcanvas",
            $salesChannelId
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        static::assertCount(1, $page->getSitemaps());
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());
        static::assertCount(1, $page->getDomainRules());

        $domainRule = $page->getDomainRules()->first();
        static::assertInstanceOf(DomainRuleStruct::class, $domainRule);
        static::assertEquals([
            ['type' => 'Disallow', 'path' => '/account/'],
            ['type' => 'Disallow', 'path' => '/checkout/'],
            ['type' => 'Disallow', 'path' => '/widgets/'],
            ['type' => 'Allow', 'path' => '/widgets/cms/'],
            ['type' => 'Allow', 'path' => '/widgets/menu/offcanvas'],
        ], $domainRule->getRules());
        static::assertSame('', $domainRule->getBasePath());
    }

    public function testLoadWithMultipleDomains(): void
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

        $domain3 = new SalesChannelDomainEntity();
        $domain3->setId('test-domain-id-3');
        $domain3->setUrl('http://example.com/en');
        $domain3->setSalesChannelId($salesChannelId2);

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$domain1, $domain2])]);

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser()
        );

        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "Disallow: /account/\nDisallow: /checkout/\nDisallow: /widgets/\nAllow: /widgets/cms/\nAllow: /widgets/menu/offcanvas",
            $salesChannelId1
        );
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "Disallow: /private/\nDisallow: /admin/\nAllow: /widgets/cms/",
            $salesChannelId2
        );

        $page = $this->robotsPageLoader->load($request, $context);

        static::assertCount(2, $page->getSitemaps());
        static::assertEquals(
            ['https://example.com/sitemap.xml', 'https://example.com/en/sitemap.xml'],
            $page->getSitemaps()
        );
        static::assertCount(2, $page->getDomainRules());
        $domainRules = $page->getDomainRules();

        static::assertCount(2, $domainRules);

        $firstDomainRule = $domainRules->get(0);
        static::assertInstanceOf(DomainRuleStruct::class, $firstDomainRule);
        static::assertSame('', $firstDomainRule->getBasePath());
        static::assertCount(5, $firstDomainRule->getRules());
        static::assertEquals(
            [
                ['type' => 'Disallow', 'path' => '/account/'],
                ['type' => 'Disallow', 'path' => '/checkout/'],
                ['type' => 'Disallow', 'path' => '/widgets/'],
                ['type' => 'Allow', 'path' => '/widgets/cms/'],
                ['type' => 'Allow', 'path' => '/widgets/menu/offcanvas'],
            ],
            $firstDomainRule->getRules()
        );

        $secondDomainRule = $domainRules->get(1);
        static::assertInstanceOf(DomainRuleStruct::class, $secondDomainRule);
        static::assertSame('/en', $secondDomainRule->getBasePath());
        static::assertCount(3, $secondDomainRule->getRules());

        static::assertEquals(
            [
                ['type' => 'Disallow', 'path' => '/en/private/'],
                ['type' => 'Disallow', 'path' => '/en/admin/'],
                ['type' => 'Allow', 'path' => '/en/widgets/cms/'],
            ],
            $secondDomainRule->getRules()
        );

        static::assertCount(0, $page->getGlobalUserAgentBlocks());
    }

    public function testLoadWithEmptyRobotsRules(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id');
        $domain->setUrl('https://example.com');
        $domain->setSalesChannelId($salesChannelId);

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('url', 'example.com'));
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$domain])]);

        // Set empty robots rules
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            '',
            $salesChannelId
        );

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser()
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        static::assertCount(1, $page->getSitemaps());
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());
        static::assertCount(0, $page->getDomainRules()); // Empty rules should not create domain rule
        static::assertCount(0, $page->getGlobalUserAgentBlocks());
    }

    public function testLoadWithHttpAndHttpsDomains(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $httpDomain = new SalesChannelDomainEntity();
        $httpDomain->setId('test-domain-id-http');
        $httpDomain->setUrl('http://example.com');
        $httpDomain->setSalesChannelId($salesChannelId);

        $httpsDomain = new SalesChannelDomainEntity();
        $httpsDomain->setId('test-domain-id-https');
        $httpsDomain->setUrl('https://example.com');
        $httpsDomain->setSalesChannelId($salesChannelId);

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$httpDomain, $httpsDomain])]);

        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "Disallow: /account/\nAllow: /public/",
            $salesChannelId
        );

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser()
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        // HTTP and HTTPS domains for same hostname should be deduplicated
        // Both should have domainHostname = '' and should be treated as the same
        static::assertCount(1, $page->getSitemaps());
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());
        static::assertCount(1, $page->getDomainRules());

        $domainRule = $page->getDomainRules()->first();
        static::assertInstanceOf(DomainRuleStruct::class, $domainRule);
        static::assertEquals([
            ['type' => 'Disallow', 'path' => '/account/'],
            ['type' => 'Allow', 'path' => '/public/'],
        ], $domainRule->getRules());
        static::assertSame('', $domainRule->getBasePath());
    }

    public function testLoadWithDomainHavingNoRobotsRules(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId = 'test-sales-channel-id';

        $domain = new SalesChannelDomainEntity();
        $domain->setId('test-domain-id');
        $domain->setUrl('https://example.com');
        $domain->setSalesChannelId($salesChannelId);

        $criteria = new Criteria();
        $criteria->addFilter(new ContainsFilter('url', 'example.com'));
        $criteria->addFilter(new EqualsFilter('salesChannel.typeId', Defaults::SALES_CHANNEL_TYPE_STOREFRONT));

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$domain])]);

        // Don't set any robots rules for this sales channel

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser()
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        static::assertCount(1, $page->getSitemaps());
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());
        static::assertCount(0, $page->getDomainRules()); // No rules should be present
        static::assertCount(0, $page->getGlobalUserAgentBlocks());
    }

    public function testLoadWithDifferentHostnames(): void
    {
        $request = new Request(server: ['HTTP_HOST' => 'example.com']);
        $context = Context::createDefaultContext();
        $salesChannelId1 = 'test-sales-channel-id-1';
        $salesChannelId2 = 'test-sales-channel-id-2';

        // Domain for example.com
        $domain1 = new SalesChannelDomainEntity();
        $domain1->setId('test-domain-id-1');
        $domain1->setUrl('https://example.com');
        $domain1->setSalesChannelId($salesChannelId1);

        // Domain for different.org (different hostname)
        $domain2 = new SalesChannelDomainEntity();
        $domain2->setId('test-domain-id-2');
        $domain2->setUrl('https://different.org');
        $domain2->setSalesChannelId($salesChannelId2);

        $this->salesChannelDomainRepository = new StaticEntityRepository([new SalesChannelDomainCollection([$domain1, $domain2])]);

        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "Disallow: /account/\nAllow: /public/",
            $salesChannelId1
        );
        $this->systemConfigService->set(
            'core.basicInformation.robotsRules',
            "Disallow: /private/\nAllow: /api/",
            $salesChannelId2
        );

        $this->robotsPageLoader = new RobotsPageLoader(
            $this->eventDispatcher,
            $this->salesChannelDomainRepository,
            $this->systemConfigService,
            new RobotsDirectiveParser()
        );

        $this->eventDispatcher->expects($this->once())
            ->method('dispatch')
            ->with(static::isInstanceOf(RobotsPageLoadedEvent::class));

        $page = $this->robotsPageLoader->load($request, $context);

        // Should only find the domain matching the hostname (example.com)
        static::assertCount(1, $page->getSitemaps());
        static::assertEquals(['https://example.com/sitemap.xml'], $page->getSitemaps());
        static::assertCount(1, $page->getDomainRules());

        $domainRule = $page->getDomainRules()->first();
        static::assertInstanceOf(DomainRuleStruct::class, $domainRule);
        static::assertEquals([
            ['type' => 'Disallow', 'path' => '/account/'],
            ['type' => 'Allow', 'path' => '/public/'],
        ], $domainRule->getRules());
        static::assertSame('', $domainRule->getBasePath());
    }
}
