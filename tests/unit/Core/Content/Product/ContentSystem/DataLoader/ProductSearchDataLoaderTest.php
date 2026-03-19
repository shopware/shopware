<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSearchDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSearchLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Search\AbstractProductSearchRoute;
use Shopware\Core\Content\Product\SalesChannel\Search\ProductSearchRouteResponse;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductSearchDataLoader::class)]
class ProductSearchDataLoaderTest extends TestCase
{
    private AbstractProductSearchRoute&MockObject $searchRoute;

    private ProductSearchDataLoader $loader;

    protected function setUp(): void
    {
        $this->searchRoute = $this->createMock(AbstractProductSearchRoute::class);
        $this->loader = new ProductSearchDataLoader($this->searchRoute);
    }

    #[TestDox('returns product_search as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductSearchString(): void
    {
        static::assertSame('product_search', ProductSearchDataLoader::getRequirementType());
    }

    #[TestDox('resolves provided data type from annotation')]
    public function testGetProvidedDataResolvesExpectedType(): void
    {
        $descriptor = ProductSearchDataLoader::getProvidedData();

        static::assertSame(ProductListingResult::class, $descriptor->className);
        static::assertSame([], $descriptor->genericParameters);
    }

    #[TestDox('returns search listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithSearchData(): void
    {
        $config = new ProductSearchLoaderConfig();
        $requirement = new DataRequirement('search', 'product_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'shoes')
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        $this->searchRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->loader->load($element, $requirement, $context, $request);

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets search term on cloned request POST body for route consumption')]
    public function testLoadSetsSearchTermOnClonedRequestBody(): void
    {
        $config = new ProductSearchLoaderConfig();
        $requirement = new DataRequirement('search', 'product_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'running shoes')
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSearchRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('running shoes', $capturedRequest->request->get('search'));
        static::assertNotSame($request, $capturedRequest);
    }

    #[TestDox('does not leak original request query parameters into the route request')]
    public function testLoadDoesNotLeakOriginalRequestQueryParams(): void
    {
        $config = new ProductSearchLoaderConfig();
        $requirement = new DataRequirement('search', 'product_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'shoes')
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request(['limit' => '24', 'p' => '3', 'order' => 'price-asc']);

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSearchRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, $request);

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('shoes', $capturedRequest->request->get('search'));
        static::assertSame([], $capturedRequest->query->all());
    }

    #[TestDox('reads search term from custom property name when configured')]
    public function testLoadUsesCustomSearchTermPropertyFromConfig(): void
    {
        $config = new ProductSearchLoaderConfig(searchTermProperty: 'query');
        $requirement = new DataRequirement('search', 'product_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('query', 'blue shirt')
            ->build();
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSearchRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('blue shirt', $capturedRequest->request->get('search'));
    }

    #[TestDox('adds config associations to criteria when loading search')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $config = new ProductSearchLoaderConfig(associations: ['manufacturer', 'cover']);
        $requirement = new DataRequirement('search', 'product_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'shoes')
            ->build();
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductSearchRouteResponse {
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
        $config = new ProductSearchLoaderConfig(associations: ['manufacturer']);
        $requirement = new DataRequirement('search', 'product_search', $config);
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', 'shoes')
            ->withProperty('associations', ['cover', 'media'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSearchRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->searchRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductSearchRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('cover', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a ProductSearchLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('search', 'product_search', $wrongConfig);
        $element = ContentElementBuilder::create('search')->build();
        $context = Generator::generateSalesChannelContext();

        $this->searchRoute->expects($this->never())->method('load');

        $result = $this->loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when search term element property is an empty string')]
    public function testLoadReturnsNotFoundWhenSearchTermPropertyIsEmptyString(): void
    {
        $config = new ProductSearchLoaderConfig();
        $element = ContentElementBuilder::create('search')
            ->withProperty('searchTerm', '')
            ->build();
        $context = Generator::generateSalesChannelContext();

        $this->searchRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('search', 'product_search', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
    }

    #[DataProvider('guardsInvalidSearchTermProvider')]
    #[TestDox('returns notFound result when searchTerm is invalid: $_dataName')]
    public function testLoadReturnsNotFoundWhenSearchTermPropertyIsInvalid(ContentElement $element): void
    {
        $config = new ProductSearchLoaderConfig();
        $context = Generator::generateSalesChannelContext();

        $this->searchRoute->expects($this->never())->method('load');

        $result = $this->loader->load(
            $element,
            new DataRequirement('search', 'product_search', $config),
            $context,
            new Request()
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @return iterable<string, array{ContentElement}>
     */
    public static function guardsInvalidSearchTermProvider(): iterable
    {
        yield 'non-string value triggers guard' => [
            ContentElementBuilder::create('search')->withProperty('searchTerm', 42)->build(),
        ];
        yield 'missing property triggers guard' => [
            ContentElementBuilder::create('search')->build(),
        ];
    }
}
