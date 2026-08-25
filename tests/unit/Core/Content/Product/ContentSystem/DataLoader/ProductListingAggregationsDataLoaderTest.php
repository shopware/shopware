<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingAggregationsDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingAggregationsLoaderConfig;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingElementLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfig;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\AggregationResultCollection;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\MaxResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductListingAggregationsDataLoader::class)]
class ProductListingAggregationsDataLoaderTest extends TestCase
{
    #[TestDox('returns product_listing_aggregations as requirement type identifier')]
    public function testGetRequirementType(): void
    {
        static::assertSame('product_listing_aggregations', ProductListingAggregationsDataLoader::getRequirementType());
    }

    /**
     * The narrower produced type is the reason this loader exists: a consumer gets the aggregations, not a
     * listing result whose products, total and sorting have been emptied by `only-aggregations`.
     */
    #[TestDox('returns the aggregations rather than the whole listing result')]
    public function testLoadReturnsOnlyTheAggregations(): void
    {
        $aggregations = new AggregationResultCollection([new MaxResult('price', 99.0)]);

        $capturedRequest = null;
        $loader = $this->loader($aggregations, $capturedRequest);

        $result = $loader->load(
            ContentElementBuilder::create('filter-panel')->withProperty('navigationId', Uuid::randomHex())->build(),
            new DataRequirement('filterAggregations', 'product_listing_aggregations', new ProductListingAggregationsLoaderConfig()),
            Generator::generateSalesChannelContext(),
            new Request()
        );

        static::assertSame($aggregations, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertTrue($capturedRequest->request->get('only-aggregations'));
    }

    #[TestDox('returns notFound result when navigationId is not a uuid')]
    public function testLoadReturnsNotFoundForUnresolvedPlaceholder(): void
    {
        $capturedRequest = null;
        $loader = $this->loader(new AggregationResultCollection(), $capturedRequest);

        $result = $loader->load(
            ContentElementBuilder::create('filter-panel')->withProperty('navigationId', '{{categoryId}}')->build(),
            new DataRequirement('filterAggregations', 'product_listing_aggregations', new ProductListingAggregationsLoaderConfig()),
            Generator::generateSalesChannelContext(),
            new Request()
        );

        static::assertNull($result->data);
        static::assertNull($capturedRequest);
    }

    #[TestDox('returns notFound result when the requirement carries another loader config')]
    public function testLoadReturnsNotFoundForForeignConfig(): void
    {
        $capturedRequest = null;
        $loader = $this->loader(new AggregationResultCollection(), $capturedRequest);

        $result = $loader->load(
            ContentElementBuilder::create('filter-panel')->withProperty('navigationId', Uuid::randomHex())->build(),
            new DataRequirement('filterAggregations', 'product_listing_aggregations', new ProductListingLoaderConfig()),
            Generator::generateSalesChannelContext(),
            new Request()
        );

        static::assertNull($result->data);
        static::assertNull($capturedRequest);
    }

    private function loader(AggregationResultCollection $aggregations, ?Request &$capturedRequest): ProductListingAggregationsDataLoader
    {
        $listing = ProductListingResult::fromSearchResult(new EntitySearchResult(
            ProductDefinition::ENTITY_NAME,
            0,
            new ProductCollection(),
            $aggregations,
            new Criteria(),
            Context::createDefaultContext()
        ), new ProductSortingCollection());

        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listing);

        $route = static::createStub(AbstractProductListingRoute::class);
        $route->method('load')->willReturnCallback(
            static function (string $id, Request $request) use (&$capturedRequest, $response): ProductListingRouteResponse {
                $capturedRequest = $request;

                return $response;
            }
        );

        return new ProductListingAggregationsDataLoader(
            new ProductListingElementLoader($route, StaticEntityRepository::of(ProductSortingCollection::class))
        );
    }
}
