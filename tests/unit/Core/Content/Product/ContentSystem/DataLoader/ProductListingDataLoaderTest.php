<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingElementLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Covers the wrapper only: config handling, the narrowing it derives from that config, and how it shapes the
 * element loader's return into a result. Request preparation and criteria building belong to
 * {@see ProductListingElementLoader} and are covered by its own test.
 *
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductListingDataLoader::class)]
class ProductListingDataLoaderTest extends TestCase
{
    #[TestDox('returns product_listing as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductListingString(): void
    {
        static::assertSame('product_listing', ProductListingDataLoader::getRequirementType());
    }

    #[TestDox('returns listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithListingData(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $navigationId)
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute
            ->expects($this->once())
            ->method('load')
            ->with($navigationId, $request, $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $loader = new ProductListingDataLoader(new ProductListingElementLoader($listingRoute, $this->emptySortingRepository()));
        $result = $loader->load($element, $requirement, $context, $request);

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when config is not a ProductListingLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('listing', 'product_listing', $wrongConfig);
        $element = ContentElementBuilder::create('product-listing')->build();
        $context = Generator::generateSalesChannelContext();

        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new ProductListingDataLoader(new ProductListingElementLoader($listingRoute, $this->emptySortingRepository()));
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when the element loader resolves no listing')]
    public function testLoadReturnsNotFoundWhenNavigationIdPropertyIsNotString(): void
    {
        $config = new ProductListingLoaderConfig(property: 'navigationId');

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', 42)
            ->build();

        $context = Generator::generateSalesChannelContext();

        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new ProductListingDataLoader(new ProductListingElementLoader($listingRoute, $this->emptySortingRepository()));
        $result = $loader->load(
            $element,
            new DataRequirement('listing', 'product_listing', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * A grid renders no aggregations, so computing them would be work thrown away. The panel beside it asks for
     * the other half, which is what keeps a page with both from running two full listing pipelines.
     */
    #[TestDox('asks the route to skip aggregations when the element renders none')]
    public function testLoadSkipsAggregationsWhenNotNeeded(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->build();

        $capturedRequest = $this->captureRequest(
            $element,
            new Request(),
            new ProductListingLoaderConfig(aggregations: false)
        );

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertTrue($capturedRequest->request->get('no-aggregations'));
        static::assertFalse($capturedRequest->request->has('only-aggregations'));
    }

    #[TestDox('narrows nothing when the element needs the whole result')]
    public function testLoadNarrowsNothingByDefault(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->build();

        $capturedRequest = $this->captureRequest($element, new Request(), new ProductListingLoaderConfig());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame([], $capturedRequest->request->all());
    }

    /**
     * @return StaticEntityRepository<ProductSortingCollection>
     */
    private function emptySortingRepository(): StaticEntityRepository
    {
        return StaticEntityRepository::of(ProductSortingCollection::class);
    }

    private function captureRequest(
        ContentElement $element,
        Request $request,
        ProductListingLoaderConfig $config
    ): ?Request {
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn(static::createStub(ProductListingResult::class));

        $capturedRequest = null;
        $listingRoute = static::createStub(AbstractProductListingRoute::class);
        $listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req) use (&$capturedRequest, $response): ProductListingRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $loader = new ProductListingDataLoader(new ProductListingElementLoader($listingRoute, $this->emptySortingRepository()));
        $loader->load(
            $element,
            new DataRequirement('listing', 'product_listing', $config),
            Generator::generateSalesChannelContext(),
            $request
        );

        return $capturedRequest;
    }
}
