<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Search\ResolvedCriteriaProductSearchRoute;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotEqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Grouping\FieldGrouping;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[CoversClass(ProductListingLoader::class)]
class ProductListingLoaderTest extends TestCase
{
    public function testScoreRankedGroupingExcludesProductsWithVariants(): void
    {
        $actual = $this->resolveSearchIds(findBestVariant: true);

        $expected = new Criteria();
        $expected->addState(ResolvedCriteriaProductSearchRoute::STATE);
        $expected->addGroupField(new FieldGrouping('displayGroup'));
        $expected->addFilter(new NotEqualsFilter('displayGroup', null));
        $expected->addState(Criteria::STATE_SCORE_RANKED_GROUPING);
        $expected->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('childCount', 0),
            new EqualsFilter('childCount', null),
        ]));

        static::assertEquals($expected, $actual);
    }

    public function testNonBestVariantSearchDoesNotExcludeProductsWithVariants(): void
    {
        $criteria = $this->resolveSearchIds(findBestVariant: false);

        static::assertFalse($criteria->hasState(Criteria::STATE_SCORE_RANKED_GROUPING));
        static::assertNull($this->findChildCountFilter($criteria));
    }

    private function resolveSearchIds(bool $findBestVariant): Criteria
    {
        $salesChannelId = Uuid::randomHex();

        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getSalesChannelId')->willReturn($salesChannelId);
        $context->method('getContext')->willReturn(Context::createDefaultContext());

        $systemConfigService = $this->createMock(SystemConfigService::class);
        $systemConfigService->method('getBool')->willReturnMap([
            ['core.listing.findBestVariant', $salesChannelId, $findBestVariant],
            ['core.listing.hideCloseoutProductsWhenOutOfStock', $salesChannelId, false],
        ]);

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([[]]);

        $loader = new ProductListingLoader(
            $productRepository,
            $systemConfigService,
            $this->createMock(Connection::class),
            new EventDispatcher(),
            $this->createMock(AbstractProductCloseoutFilterFactory::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $criteria = new Criteria();
        $criteria->addState(ResolvedCriteriaProductSearchRoute::STATE);

        $method = new \ReflectionMethod($loader, 'resolveIds');
        $method->invoke($loader, $criteria, $context);

        return $criteria;
    }

    private function findChildCountFilter(Criteria $criteria): ?MultiFilter
    {
        foreach ($criteria->getFilters() as $filter) {
            if (!$filter instanceof MultiFilter) {
                continue;
            }

            if (\in_array('childCount', $filter->getFields(), true)) {
                return $filter;
            }
        }

        return null;
    }
}
