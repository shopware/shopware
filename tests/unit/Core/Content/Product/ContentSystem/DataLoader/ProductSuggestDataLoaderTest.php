<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSuggestDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductSuggestLoaderConfig;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Suggest\AbstractProductSuggestRoute;
use Shopware\Core\Content\Product\SalesChannel\Suggest\ProductSuggestRouteResponse;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\Framework\Script\ScriptException;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(ProductSuggestDataLoader::class)]
class ProductSuggestDataLoaderTest extends TestCase
{
    private AbstractProductSuggestRoute&Stub $suggestRoute;

    private ProductSuggestDataLoader $loader;

    protected function setUp(): void
    {
        $this->suggestRoute = static::createStub(AbstractProductSuggestRoute::class);
        $this->loader = new ProductSuggestDataLoader($this->suggestRoute);
    }

    #[TestDox('returns product_suggest as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductSuggestString(): void
    {
        static::assertSame('product_suggest', ProductSuggestDataLoader::getRequirementType());
    }

    #[TestDox('declares ProductListingResult as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(ProductListingResult::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('returns suggest listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithSuggestData(): void
    {
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        $this->suggestRoute
            ->method('load')
            ->willReturn($response);

        $result = $this->loader->load(
            new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('sets search term on cloned request POST body for route consumption')]
    public function testLoadSetsSearchTermOnClonedRequestBody(): void
    {
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSuggestRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['searchTermProperty' => 'running shoes', 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('running shoes', $capturedRequest->request->get('search'));
        static::assertNotSame($request, $capturedRequest);
    }

    #[TestDox('does not leak original request query parameters into the route request')]
    public function testLoadDoesNotLeakOriginalRequestQueryParams(): void
    {
        $context = Generator::generateSalesChannelContext();
        $request = new Request(['limit' => '24', 'p' => '3', 'order' => 'price-asc']);

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSuggestRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('shoes', $capturedRequest->request->get('search'));
        static::assertSame([], $capturedRequest->query->all());
    }

    #[TestDox('dereferences the element property the config names into the search term')]
    public function testLoadUsesCustomSearchTermPropertyFromConfig(): void
    {
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSuggestRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $inputs = $this->resolve(
            new ProductSuggestLoaderConfig(searchTermProperty: 'query'),
            ['query' => 'blue shirt'],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('blue shirt', $capturedRequest->request->get('search'));
    }

    #[TestDox('resolves an unset searchTermProperty to the declared searchTerm default')]
    public function testUnsetSearchTermPropertyResolvesToDeclaredSearchTermDefault(): void
    {
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Request|null $capturedRequest */
        $capturedRequest = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req) use (&$capturedRequest, $response): ProductSuggestRouteResponse {
                $capturedRequest = $req;

                return $response;
            });

        $inputs = $this->resolve(
            new ProductSuggestLoaderConfig(),
            ['searchTerm' => 'winter jacket'],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Request::class, $capturedRequest);
        static::assertSame('winter jacket', $capturedRequest->request->get('search'));
    }

    #[TestDox('adds every configured association to the criteria')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductSuggestRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => ['manufacturer', 'cover']]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('appends the associations element property after the configured associations by default')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $context = Generator::generateSalesChannelContext();

        $listingResult = static::createStub(ProductListingResult::class);
        $response = static::createStub(ProductSuggestRouteResponse::class);
        $response->method('getListingResult')->willReturn($listingResult);

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $this->suggestRoute
            ->method('load')
            ->willReturnCallback(static function (Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductSuggestRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $inputs = $this->resolve(
            new ProductSuggestLoaderConfig(associations: ['manufacturer']),
            ['searchTerm' => 'winter jacket', 'associations' => ['cover', 'media']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover', 'media'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('returns notFound result when the search term resolves to an empty string')]
    public function testLoadReturnsNotFoundWhenSearchTermIsEmptyString(): void
    {
        $context = Generator::generateSalesChannelContext();

        $suggestRoute = $this->createMock(AbstractProductSuggestRoute::class);
        $suggestRoute->expects($this->never())->method('load');

        $loader = new ProductSuggestDataLoader($suggestRoute);
        $result = $loader->load(
            new LoaderInputs(['searchTermProperty' => '', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when the search term input is unresolved')]
    public function testLoadReturnsNotFoundWhenSearchTermInputIsUnresolved(): void
    {
        $context = Generator::generateSalesChannelContext();

        $suggestRoute = $this->createMock(AbstractProductSuggestRoute::class);
        $suggestRoute->expects($this->never())->method('load');

        $loader = new ProductSuggestDataLoader($suggestRoute);
        $result = $loader->load(
            new LoaderInputs(['searchTermProperty' => null, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the suggest route throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenSuggestRouteThrows(\Throwable $exception): void
    {
        $context = Generator::generateSalesChannelContext();

        $suggestRoute = $this->createMock(AbstractProductSuggestRoute::class);
        $suggestRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $loader = new ProductSuggestDataLoader($suggestRoute);
        $result = $loader->load(
            new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the suggest route propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $context = Generator::generateSalesChannelContext();

        $typeError = new \TypeError('Argument #3 ($criteria) must be of type Criteria, null given');

        $suggestRoute = $this->createMock(AbstractProductSuggestRoute::class);
        $suggestRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($typeError);

        $loader = new ProductSuggestDataLoader($suggestRoute);

        try {
            $loader->load(
                new LoaderInputs(['searchTermProperty' => 'shoes', 'associations' => []]),
                self::requirement(),
                $context,
                new Request(),
            );

            static::fail('Expected the TypeError to propagate out of load() instead of degrading to notFound');
        } catch (\TypeError $caught) {
            static::assertSame($typeError, $caught);
        }
    }

    /**
     * Sample domain exceptions off the suggest chain, not one row per catch arm: the loader catches the
     * single covering ancestor `ShopwareHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // Reachable via CompositeListingProcessor::prepare() -> SortingListingProcessor::prepare() when
        // the configured default sorting id points to a deleted sorting entity; SortingListingProcessor
        // itself calls the factory with an empty key. Not flag-dependent.
        yield 'default sorting entity missing' => [ProductException::sortingNotFoundException('')];

        // Flag-off form of ProductException::missingRequestParameter('search'), thrown directly rather than
        // via the factory so this row holds regardless of v6.8.0.0 state.
        yield 'missing search parameter, flag-off form' => [RoutingException::missingRequestParameter('search')];

        // AppScriptProductPriceCalculator decorates ProductPriceCalculator on the suggest chain, and
        // ScriptExecutor rewraps any Throwable an app script raises into ScriptExecutionFailedException, so
        // no enumeration of the chain's own exception classes can cover it.
        yield 'app script failure rewrapped as ScriptExecutionFailedException' => [
            ScriptException::scriptExecutionFailed('product-pricing', 'product-pricing.twig', new \RuntimeException('app script failed')),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(ProductSuggestLoaderConfig $config, array $properties): LoaderInputs
    {
        return (new LoaderInputResolver())->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('suggest', 'product_suggest', new ProductSuggestLoaderConfig());
    }
}
