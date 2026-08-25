<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingElementLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
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
 * @internal
 */
#[Package('framework')]
#[CoversClass(ProductListingDataLoader::class)]
class ProductListingDataLoaderTest extends TestCase
{
    private AbstractProductListingRoute&Stub $listingRoute;

    private ProductListingDataLoader $loader;

    protected function setUp(): void
    {
        $this->listingRoute = static::createStub(AbstractProductListingRoute::class);
        $this->loader = new ProductListingDataLoader(new ProductListingElementLoader($this->listingRoute, $this->emptySortingRepository()));
    }

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

    #[TestDox('lowercases navigationId before passing it to the listing route')]
    public function testLoadCallsListingRouteWithLowercasedNavigationId(): void
    {
        $navigationId = Uuid::randomHex();
        $upperCaseId = strtoupper($navigationId);

        $config = new ProductListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $upperCaseId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $capturedNavigationId = null;
        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId) use (&$capturedNavigationId, $response): ProductListingRouteResponse {
                $capturedNavigationId = $catId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($navigationId, $capturedNavigationId);
    }

    #[TestDox('reads navigationId from custom property name when configured')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig(property: 'categoryId');
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('categoryId', $navigationId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $capturedCategoryId = null;
        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId) use (&$capturedCategoryId, $response): ProductListingRouteResponse {
                $capturedCategoryId = $catId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($navigationId, $capturedCategoryId);
    }

    #[TestDox('adds config associations to criteria when loading listing')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig(associations: ['manufacturer', 'cover']);
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $navigationId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
    }

    #[TestDox('merges element associations property into criteria when it is an array of strings')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig(associations: ['manufacturer']);
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $navigationId)
            ->withProperty('associations', ['cover', 'media'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('ignores non-string values in element associations array when building criteria')]
    public function testLoadIgnoresNonStringValuesInElementAssociations(): void
    {
        $navigationId = Uuid::randomHex();

        $config = new ProductListingLoaderConfig();
        $requirement = new DataRequirement('listing', 'product_listing', $config);
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', $navigationId)
            ->withProperty('associations', ['cover', 42, null, 'media'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn($listingResult);

        $this->listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
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

    #[TestDox('returns notFound result when navigationId element property is not a string')]
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
     * The "{{categoryId}}" default is seeded into every stored tree and stays literal on a layout not rooted on
     * a category. Reaching the route with it would abort the whole render instead of degrading to no listing.
     */
    #[TestDox('returns notFound result when navigationId is not a uuid, such as an unresolved placeholder')]
    public function testLoadReturnsNotFoundWhenNavigationIdIsNotAUuid(): void
    {
        $config = new ProductListingLoaderConfig(property: 'navigationId');

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', '{{categoryId}}')
            ->build();

        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new ProductListingDataLoader(new ProductListingElementLoader($listingRoute, $this->emptySortingRepository()));
        $result = $loader->load(
            $element,
            new DataRequirement('listing', 'product_listing', $config),
            Generator::generateSalesChannelContext(),
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets the element defaultSorting as order parameter on the shared request when it carries none')]
    public function testLoadAppliesDefaultSortingWhenRequestHasNoOrder(): void
    {
        $sortingId = Uuid::randomHex();

        $sorting = new ProductSortingEntity();
        $sorting->setUniqueIdentifier($sortingId);
        $sorting->setId($sortingId);
        $sorting->setKey('price-asc');

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('defaultSorting', $sortingId)
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection([$sorting]), $element, $request = new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('price-asc', $capturedRequest->request->get('order'));
        static::assertSame('price-asc', $request->request->get('order'), 'later listing elements must see the order');
    }

    #[TestDox('keeps an order the request already carries instead of applying the element defaultSorting')]
    public function testLoadKeepsRequestOrderOverDefaultSorting(): void
    {
        $sorting = new ProductSortingEntity();
        $sorting->setUniqueIdentifier(Uuid::randomHex());
        $sorting->setKey('price-asc');

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('defaultSorting', Uuid::randomHex())
            ->build();

        $request = new Request(['order' => 'name-desc']);
        $capturedRequest = $this->captureRequest(new ProductSortingCollection([$sorting]), $element, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('name-desc', $capturedRequest->query->get('order'));
        static::assertFalse($capturedRequest->request->has('order'));
    }

    #[TestDox('switches off the filter handler of every toggle the element disables')]
    public function testLoadDisablesFilterHandlersForDisabledToggles(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('showManufacturerFilter', false)
            ->withProperty('showPropertyFilter', false)
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->request->get('manufacturer-filter'));
        static::assertFalse($capturedRequest->request->get('property-filter'));
        static::assertNull($capturedRequest->request->get('price-filter'));
        static::assertNull($capturedRequest->request->get('rating-filter'));
        static::assertNull($capturedRequest->request->get('shipping-free-filter'));
    }

    #[TestDox('restricts the property filters to the property group ids the element whitelists')]
    public function testLoadPassesPropertyWhitelistToTheListingRoute(): void
    {
        $groupIds = [Uuid::randomHex(), Uuid::randomHex()];

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('propertyWhitelist', implode(',', $groupIds))
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame($groupIds, $capturedRequest->request->all('property-whitelist'));
    }

    #[TestDox('trims and drops empty entries from the comma-separated property whitelist')]
    public function testLoadFiltersInvalidEntriesFromPropertyWhitelist(): void
    {
        $groupId = Uuid::randomHex();

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('propertyWhitelist', $groupId . ' , ,')
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame([$groupId], $capturedRequest->request->all('property-whitelist'));
    }

    #[TestDox('withholds the property whitelist when the property filter toggle is off')]
    public function testLoadWithholdsPropertyWhitelistWhenPropertyFilterIsDisabled(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('showPropertyFilter', false)
            ->withProperty('propertyWhitelist', Uuid::randomHex())
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->request->get('property-filter'));
        static::assertSame([], $capturedRequest->request->all('property-whitelist'));
    }

    #[TestDox('leaves the request untouched when every filter toggle is enabled')]
    public function testLoadDoesNotTouchRequestWhenAllTogglesEnabled(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('showManufacturerFilter', true)
            ->withProperty('showPropertyFilter', true)
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, $request = new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertNotSame($request, $capturedRequest, 'the route must never receive the shared request');
        static::assertSame([], $capturedRequest->request->all());
    }

    #[TestDox('leaves the request untouched when the element defaultSorting matches no product sorting')]
    public function testLoadLeavesRequestUntouchedWhenDefaultSortingIsUnknown(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('defaultSorting', Uuid::randomHex())
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, $request = new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertNotSame($request, $capturedRequest);
        static::assertFalse($capturedRequest->request->has('order'));
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
            new ProductSortingCollection(),
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

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, new Request());

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
        ProductSortingCollection $sortings,
        ContentElement $element,
        Request $request,
        ?ProductListingLoaderConfig $config = null
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

        $loader = new ProductListingDataLoader(new ProductListingElementLoader($listingRoute, new StaticEntityRepository([$sortings])));
        $loader->load(
            $element,
            new DataRequirement('listing', 'product_listing', $config ?? new ProductListingLoaderConfig()),
            Generator::generateSalesChannelContext(),
            $request
        );

        return $capturedRequest;
    }
}
