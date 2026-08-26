<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingElementLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PriceListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\Filter\PropertyListingFilterHandler;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingCollection;
use Shopware\Core\Content\Product\SalesChannel\Sorting\ProductSortingEntity;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
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
#[CoversClass(ProductListingElementLoader::class)]
class ProductListingElementLoaderTest extends TestCase
{
    #[TestDox('lowercases navigationId before passing it to the listing route')]
    public function testLoadCallsListingRouteWithLowercasedNavigationId(): void
    {
        $navigationId = Uuid::randomHex();

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', mb_strtoupper($navigationId))
            ->build();

        static::assertSame($navigationId, $this->captureNavigationId($element));
    }

    #[TestDox('reads navigationId from the given property name')]
    public function testLoadReadsNavigationIdFromTheGivenPropertyName(): void
    {
        $navigationId = Uuid::randomHex();

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('categoryId', $navigationId)
            ->build();

        static::assertSame($navigationId, $this->captureNavigationId($element, 'categoryId'));
    }

    #[TestDox('returns null when the navigationId element property is not a string')]
    public function testLoadReturnsNullWhenNavigationIdIsNotAString(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', 42)
            ->build();

        static::assertNull($this->loadWithoutRoute($element));
    }

    /**
     * The "{{categoryId}}" default is seeded into every stored tree and stays literal on a layout not rooted on
     * a category. Reaching the route with it would abort the whole render instead of degrading to no listing.
     */
    #[TestDox('returns null when navigationId is not a uuid, such as an unresolved placeholder')]
    public function testLoadReturnsNullWhenNavigationIdIsNotAUuid(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', '{{categoryId}}')
            ->build();

        static::assertNull($this->loadWithoutRoute($element));
    }

    #[TestDox('adds caller associations to criteria when loading listing')]
    public function testLoadAddsCallerAssociationsToCriteria(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->build();

        $capturedCriteria = $this->captureCriteria($element, ['manufacturer', 'cover']);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
    }

    #[TestDox('merges element associations property into criteria when it is an array of strings')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('associations', ['cover', 'media'])
            ->build();

        $capturedCriteria = $this->captureCriteria($element, ['manufacturer']);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('ignores non-string values in element associations array when building criteria')]
    public function testLoadIgnoresNonStringValuesInElementAssociations(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('associations', ['cover', 42, null, 'media'])
            ->build();

        $capturedCriteria = $this->captureCriteria($element);

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
        static::assertCount(2, $capturedCriteria->getAssociations());
    }

    #[TestDox('sets the element defaultSorting as order parameter on this element request only')]
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
        static::assertFalse(
            $request->request->has('order'),
            'a later listing element without its own defaultSorting must fall back to the sales channel default'
        );
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

    /**
     * Only the query string carries a visitor's own choice. The Storefront renders the classic navigation page
     * before the content layout, and that run leaves the sales channel default in the request bag, so a stale
     * bag entry must lose against the element's own configuration.
     */
    #[TestDox('overwrites a stale order in the request bag with the element defaultSorting')]
    public function testLoadOverwritesStaleBagOrderWithDefaultSorting(): void
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

        $request = new Request([], ['order' => 'topseller']);
        $capturedRequest = $this->captureRequest(new ProductSortingCollection([$sorting]), $element, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('price-asc', $capturedRequest->request->get('order'));
        static::assertSame('topseller', $request->request->get('order'), 'the shared request stays as it was');
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

    #[TestDox('ignores an empty-string defaultSorting')]
    public function testLoadIgnoresEmptyStringDefaultSorting(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('defaultSorting', '')
            ->build();

        $capturedRequest = $this->captureRequest(null, $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->request->has('order'));
    }

    #[TestDox('ignores a defaultSorting that is not a uuid instead of reaching the DAL with it')]
    public function testLoadIgnoresADefaultSortingThatIsNotAUuid(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('defaultSorting', 'name-asc')
            ->build();

        $capturedRequest = $this->captureRequest(null, $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->request->has('order'));
    }

    #[TestDox('does not inherit a filter flag an earlier listing run left in the request bag')]
    public function testLoadDropsAStaleFilterFlagFromTheRequestBag(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->build();

        $request = new Request([], [PriceListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM => false]);
        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->request->has(PriceListingFilterHandler::FILTER_ENABLED_REQUEST_PARAM));
    }

    #[TestDox('does not inherit a property whitelist or available sortings from the request bag')]
    public function testLoadDropsAStaleWhitelistAndAvailableSortingsFromTheRequestBag(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->build();

        $request = new Request([], [
            PropertyListingFilterHandler::PROPERTY_GROUP_IDS_REQUEST_PARAM => [Uuid::randomHex()],
            'availableSortings' => [Uuid::randomHex() => 5],
        ]);
        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->request->has(PropertyListingFilterHandler::PROPERTY_GROUP_IDS_REQUEST_PARAM));
        static::assertFalse($capturedRequest->request->has('availableSortings'));
    }

    /**
     * Without this the element falls back to another listing's sorting instead of the sales channel default.
     */
    #[TestDox('does not inherit the aggregation behaviour of an earlier listing run')]
    public function testLoadDropsStaleAggregationFlagsFromTheRequestBag(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->build();

        // Set by SearchController, CmsController and WishlistController for their own listings.
        $request = new Request([], ['only-aggregations' => true, 'no-aggregations' => true]);
        $capturedRequest = $this->captureRequest(null, $element, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->request->has('only-aggregations'));
        static::assertFalse($capturedRequest->request->has('no-aggregations'));
    }

    #[TestDox('does not inherit a stale order when the element declares no defaultSorting')]
    public function testLoadDropsAStaleOrderWhenTheElementDeclaresNoDefaultSorting(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->build();

        $request = new Request([], ['order' => 'topseller']);
        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertFalse($capturedRequest->request->has('order'));
        static::assertSame('topseller', $request->request->get('order'), 'the shared request stays as it was');
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

    #[TestDox('restricts the property filters to the property group ids the element allows')]
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

    #[TestDox('trims and drops empty entries from the comma-separated property group ids')]
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

    /**
     * A non-uuid entry, such as an unresolved placeholder or a property group name, would reach
     * Uuid::fromHexToBytes through the property filter handler and abort the whole render, while a
     * loader must never throw.
     */
    #[TestDox('drops entries that are not uuids from the property group ids')]
    public function testLoadDropsNonUuidEntriesFromPropertyWhitelist(): void
    {
        $groupId = Uuid::randomHex();

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('propertyWhitelist', $groupId . ',{{propertyGroupId}},not-a-uuid')
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame([$groupId], $capturedRequest->request->all('property-whitelist'));
    }

    #[TestDox('withholds the property group ids when no entry is a uuid')]
    public function testLoadWithholdsPropertyWhitelistWhenNoEntryIsAUuid(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('propertyWhitelist', '{{propertyGroupId}},not-a-uuid')
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame([], $capturedRequest->request->all('property-whitelist'));
    }

    #[TestDox('lowercases property group ids before validating them as uuids')]
    public function testLoadLowercasesPropertyWhitelistEntries(): void
    {
        $groupId = Uuid::randomHex();

        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->withProperty('propertyWhitelist', mb_strtoupper($groupId))
            ->build();

        $capturedRequest = $this->captureRequest(new ProductSortingCollection(), $element, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame([$groupId], $capturedRequest->request->all('property-whitelist'));
    }

    #[TestDox('withholds the property group ids when the property filter toggle is off')]
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

    #[TestDox('sets caller parameters on the duplicated request without touching the shared one')]
    public function testLoadSetsCallerParametersOnTheDuplicatedRequest(): void
    {
        $element = ContentElementBuilder::create('product-listing')
            ->withProperty('navigationId', Uuid::randomHex())
            ->build();

        $capturedRequest = $this->captureRequest(
            new ProductSortingCollection(),
            $element,
            $request = new Request(),
            ['only-aggregations' => true]
        );

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertNotSame($request, $capturedRequest);
        static::assertTrue($capturedRequest->request->get('only-aggregations'));
        static::assertFalse($request->request->has('only-aggregations'));
    }

    /**
     * @return StaticEntityRepository<ProductSortingCollection>
     */
    private function emptySortingRepository(): StaticEntityRepository
    {
        return StaticEntityRepository::of(ProductSortingCollection::class);
    }

    private function loadWithoutRoute(ContentElement $element): ?ProductListingResult
    {
        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new ProductListingElementLoader($listingRoute, $this->emptySortingRepository());

        return $loader->load($element, Generator::generateSalesChannelContext(), new Request());
    }

    private function captureNavigationId(ContentElement $element, ?string $propertyName = null): ?string
    {
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn(static::createStub(ProductListingResult::class));

        $capturedNavigationId = null;
        $listingRoute = static::createStub(AbstractProductListingRoute::class);
        $listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId) use (&$capturedNavigationId, $response): ProductListingRouteResponse {
                $capturedNavigationId = $catId;

                return $response;
            });

        $loader = new ProductListingElementLoader($listingRoute, $this->emptySortingRepository());
        $loader->load($element, Generator::generateSalesChannelContext(), new Request(), $propertyName);

        return $capturedNavigationId;
    }

    /**
     * @param list<string> $associations
     */
    private function captureCriteria(ContentElement $element, array $associations = []): ?Criteria
    {
        $response = static::createStub(ProductListingRouteResponse::class);
        $response->method('getResult')->willReturn(static::createStub(ProductListingResult::class));

        $capturedCriteria = null;
        $listingRoute = static::createStub(AbstractProductListingRoute::class);
        $listingRoute
            ->method('load')
            ->willReturnCallback(static function (string $catId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductListingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $loader = new ProductListingElementLoader($listingRoute, $this->emptySortingRepository());
        $loader->load($element, Generator::generateSalesChannelContext(), new Request(), null, $associations);

        return $capturedCriteria;
    }

    /**
     * @param array<string, bool|list<string>> $parameters
     */
    private function captureRequest(
        ?ProductSortingCollection $sortings,
        ContentElement $element,
        Request $request,
        array $parameters = []
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

        $repository = $sortings === null
            // No prepared search: the stub throws, so a lookup that should not happen fails the test.
            ? StaticEntityRepository::of(ProductSortingCollection::class, [])
            : new StaticEntityRepository([$sortings]);

        $loader = new ProductListingElementLoader($listingRoute, $repository);
        $loader->load($element, Generator::generateSalesChannelContext(), $request, null, [], $parameters);

        return $capturedRequest;
    }
}
