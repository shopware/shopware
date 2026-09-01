<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductListingLoaderConfig;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Listing\AbstractProductListingRoute;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRouteResponse;
use Shopware\Core\Content\ProductStream\ProductStreamException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
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
        $this->loader = new ProductListingDataLoader($this->listingRoute);
    }

    #[TestDox('returns product_listing as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductListingString(): void
    {
        static::assertSame('product_listing', ProductListingDataLoader::getRequirementType());
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

    #[TestDox('returns listing result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithListingData(): void
    {
        $navigationId = Uuid::randomHex();

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

        $loader = new ProductListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => $navigationId, 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertSame($listingResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('degrades an empty-string navigationId via uuid validation, not the null-input check')]
    public function testLoadDegradesEmptyStringNavigationIdViaUuidValidation(): void
    {
        $context = Generator::generateSalesChannelContext();

        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new ProductListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => '', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases navigationId before passing it to the listing route')]
    public function testLoadCallsListingRouteWithLowercasedNavigationId(): void
    {
        $navigationId = Uuid::randomHex();
        $upperCaseId = strtoupper($navigationId);

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

        $this->loader->load(
            new LoaderInputs(['property' => $upperCaseId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertSame($navigationId, $capturedNavigationId);
    }

    #[TestDox('dereferences the element property the config names into the navigation ID')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $context = Generator::generateSalesChannelContext();
        $categoryId = Uuid::randomHex();

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

        $inputs = $this->resolve(
            new ProductListingLoaderConfig(property: 'categoryId'),
            ['categoryId' => $categoryId],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($categoryId, $capturedCategoryId);
    }

    #[TestDox('resolves an unset property to the declared navigationId default')]
    public function testUnsetPropertyResolvesToDeclaredNavigationIdDefault(): void
    {
        $context = Generator::generateSalesChannelContext();
        $navigationId = Uuid::randomHex();

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

        $inputs = $this->resolve(new ProductListingLoaderConfig(), ['navigationId' => $navigationId]);
        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($navigationId, $capturedNavigationId);
    }

    #[TestDox('adds every configured association to the criteria')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $navigationId = Uuid::randomHex();

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

        $this->loader->load(
            new LoaderInputs(['property' => $navigationId, 'associations' => ['manufacturer', 'cover']]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('builds a criteria carrying no associations when none are configured')]
    public function testLoadBuildsEmptyCriteriaWhenNoAssociationsConfigured(): void
    {
        $navigationId = Uuid::randomHex();

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

        $this->loader->load(
            new LoaderInputs(['property' => $navigationId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame([], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('appends the resolved associationOverride entries after the configured associations')]
    public function testAssociationOverrideEntriesFollowConfiguredAssociationsInCriteria(): void
    {
        $context = Generator::generateSalesChannelContext();
        $navigationId = Uuid::randomHex();

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

        $inputs = $this->resolve(
            new ProductListingLoaderConfig(associations: ['media'], associationOverride: 'extraAssociations'),
            ['navigationId' => $navigationId, 'extraAssociations' => ['cover']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['media', 'cover'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('appends the associations element property after the configured associations by default')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $context = Generator::generateSalesChannelContext();
        $navigationId = Uuid::randomHex();

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

        $inputs = $this->resolve(
            new ProductListingLoaderConfig(associations: ['manufacturer']),
            ['navigationId' => $navigationId, 'associations' => ['cover', 'media']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover', 'media'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('returns notFound result when the navigation ID input is unresolved')]
    public function testLoadReturnsNotFoundWhenNavigationIdInputIsUnresolved(): void
    {
        $context = Generator::generateSalesChannelContext();

        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new ProductListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => null, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when the resolved property is not a valid uuid')]
    public function testLoadReturnsNotFoundWhenPropertyIsNotValidUuid(): void
    {
        $context = Generator::generateSalesChannelContext();

        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute->expects($this->never())->method('load');

        $loader = new ProductListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => '{{categoryId}}', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('catchArmProvider')]
    #[TestDox('returns notFound result when the listing route throws $_dataName')]
    public function testLoadReturnsNotFoundWhenListingRouteThrows(\Throwable $exception): void
    {
        $navigationId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $listingRoute = $this->createMock(AbstractProductListingRoute::class);
        $listingRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $loader = new ProductListingDataLoader($listingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => $navigationId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @return iterable<string, array{\Throwable}>
     */
    public static function catchArmProvider(): iterable
    {
        yield 'category not found' => [ProductException::categoryNotFound('category-missing')];

        yield 'product stream not found' => [ProductStreamException::productStreamNotFound('stream-missing')];

        yield 'product stream has no filters' => [ProductStreamException::noFilters('stream-no-filters')];

        yield 'product stream is empty' => [ProductStreamException::emptyProductStream('stream-empty')];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(ProductListingLoaderConfig $config, array $properties): LoaderInputs
    {
        return (new LoaderInputResolver())->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('listing', 'product_listing', new ProductListingLoaderConfig());
    }
}
