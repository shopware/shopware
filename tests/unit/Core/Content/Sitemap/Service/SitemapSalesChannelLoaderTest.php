<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Sitemap\Service;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Sitemap\Event\SitemapSalesChannelCriteriaEvent;
use Shopware\Core\Content\Sitemap\Service\SitemapSalesChannelLoader;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\AndFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\OrFilter;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainCollection;
use Shopware\Core\System\SalesChannel\Aggregate\SalesChannelDomain\SalesChannelDomainEntity;
use Shopware\Core\System\SalesChannel\SalesChannelCollection;
use Shopware\Core\System\SalesChannel\SalesChannelEntity;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(SitemapSalesChannelLoader::class)]
class SitemapSalesChannelLoaderTest extends TestCase
{
    public function testLoadSalesChannelsBuildsHeadlessAwareCriteriaAndDispatchesEvent(): void
    {
        $repository = new StaticEntityRepository([new SalesChannelCollection([])]);

        $capturedCriteria = null;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(SitemapSalesChannelCriteriaEvent::class, static function (SitemapSalesChannelCriteriaEvent $event) use (&$capturedCriteria): void {
            $capturedCriteria = $event->getCriteria();
        });

        $loader = new SitemapSalesChannelLoader($repository, $dispatcher);
        $loader->loadSalesChannels(Context::createDefaultContext());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);

        $filters = $capturedCriteria->getFilters();
        static::assertCount(2, $filters);
        static::assertInstanceOf(NotEqualsFilter::class, $filters[0]);

        $typeFilter = $filters[1];
        static::assertInstanceOf(OrFilter::class, $typeFilter);

        [$storefrontFilter, $headlessFilter] = $typeFilter->getQueries();
        static::assertInstanceOf(EqualsFilter::class, $storefrontFilter);
        static::assertSame(Defaults::SALES_CHANNEL_TYPE_STOREFRONT, $storefrontFilter->getValue());

        static::assertInstanceOf(AndFilter::class, $headlessFilter);
        [$apiTypeFilter, $externalDomainFilter] = $headlessFilter->getQueries();
        static::assertInstanceOf(EqualsFilter::class, $apiTypeFilter);
        static::assertSame(Defaults::SALES_CHANNEL_TYPE_API, $apiTypeFilter->getValue());
        static::assertInstanceOf(EqualsFilter::class, $externalDomainFilter);
        static::assertSame('domains.isExternalStorefront', $externalDomainFilter->getField());
        static::assertTrue($externalDomainFilter->getValue());
    }

    /**
     * @return \Generator<string, array{string, list<array{string, bool}>, list<string>}>
     */
    public static function languageIdCases(): \Generator
    {
        yield 'storefront channels use every domain language once' => [
            Defaults::SALES_CHANNEL_TYPE_STOREFRONT,
            [['language-a', false], ['language-a', false], ['language-b', false]],
            ['language-a', 'language-b'],
        ];

        yield 'headless channels use only external storefront domain languages' => [
            Defaults::SALES_CHANNEL_TYPE_API,
            [['language-a', true], ['language-b', false]],
            ['language-a'],
        ];

        yield 'headless channels without external storefront domain have no languages' => [
            Defaults::SALES_CHANNEL_TYPE_API,
            [['language-a', false]],
            [],
        ];
    }

    /**
     * @param list<array{string, bool}> $domainSpecs
     * @param list<string> $expectedLanguageIds
     */
    #[DataProvider('languageIdCases')]
    public function testGetLanguageIds(string $typeId, array $domainSpecs, array $expectedLanguageIds): void
    {
        $domains = new SalesChannelDomainCollection();
        foreach ($domainSpecs as [$languageId, $isExternalStorefront]) {
            $domain = new SalesChannelDomainEntity();
            $domain->setId(Uuid::randomHex());
            $domain->setLanguageId($languageId);
            $domain->setIsExternalStorefront($isExternalStorefront);
            $domains->add($domain);
        }

        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setTypeId($typeId);
        $salesChannel->setDomains($domains);

        $loader = new SitemapSalesChannelLoader(
            new StaticEntityRepository([new SalesChannelCollection([])]),
            new EventDispatcher()
        );

        static::assertSame($expectedLanguageIds, $loader->getLanguageIds($salesChannel));
    }

    public function testGetLanguageIdsWithoutLoadedDomains(): void
    {
        $salesChannel = new SalesChannelEntity();
        $salesChannel->setId(Uuid::randomHex());
        $salesChannel->setTypeId(Defaults::SALES_CHANNEL_TYPE_STOREFRONT);

        $loader = new SitemapSalesChannelLoader(
            new StaticEntityRepository([new SalesChannelCollection([])]),
            new EventDispatcher()
        );

        static::assertSame([], $loader->getLanguageIds($salesChannel));
    }
}
