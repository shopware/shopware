<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductReview\ProductReviewCollection;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfig;
use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewRoute;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewRouteResponse;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewDataLoader::class)]
class ProductReviewDataLoaderTest extends TestCase
{
    private AbstractProductReviewRoute&Stub $productReviewRoute;

    private ProductReviewDataLoader $loader;

    protected function setUp(): void
    {
        $this->productReviewRoute = static::createStub(AbstractProductReviewRoute::class);
        $this->loader = new ProductReviewDataLoader($this->productReviewRoute);
    }

    #[TestDox('returns product_review as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductReviewString(): void
    {
        static::assertSame('product_review', ProductReviewDataLoader::getRequirementType());
    }

    #[TestDox('declares an EntitySearchResult of ProductReviewCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(EntitySearchResult::class, $capabilities[0]->producedType);
        static::assertSame([ProductReviewCollection::class], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('returns review search result as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithReviewData(): void
    {
        $productId = 'product-alice';

        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $productReviewRoute = $this->createMock(AbstractProductReviewRoute::class);
        $productReviewRoute
            ->expects($this->once())
            ->method('load')
            ->with($productId, $request, $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $loader = new ProductReviewDataLoader($productReviewRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => $productId, 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertSame($reviewResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases productId before passing it to the review route')]
    public function testLoadCallsReviewRouteWithLowercasedProductId(): void
    {
        $productId = 'product-alice';
        $upperCaseId = strtoupper($productId);

        $context = Generator::generateSalesChannelContext();

        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $capturedProductId = null;
        $this->productReviewRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductReviewRouteResponse {
                $capturedProductId = $prodId;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['property' => $upperCaseId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertSame($productId, $capturedProductId);
    }

    #[TestDox('dereferences the element property the config names into the product ID')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $context = Generator::generateSalesChannelContext();

        $capturedProductId = null;
        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $this->productReviewRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductReviewRouteResponse {
                $capturedProductId = $prodId;

                return $response;
            });

        $inputs = $this->resolve(
            new ProductReviewLoaderConfig(property: 'reviewProductId'),
            ['reviewProductId' => 'product-alice'],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame('product-alice', $capturedProductId);
    }

    #[TestDox('resolves an unset property to the declared productId default')]
    public function testUnsetPropertyResolvesToDeclaredProductIdDefault(): void
    {
        $context = Generator::generateSalesChannelContext();

        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $productReviewRoute = $this->createMock(AbstractProductReviewRoute::class);
        $productReviewRoute
            ->expects($this->once())
            ->method('load')
            ->with('product-alice', static::isInstanceOf(Request::class), $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $loader = new ProductReviewDataLoader($productReviewRoute);
        $inputs = $this->resolve(new ProductReviewLoaderConfig(), ['productId' => 'product-alice']);

        $result = $loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($reviewResult, $result->data);
    }

    #[TestDox('adds every configured association to the criteria')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $productId = 'product-alice';

        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $this->productReviewRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductReviewRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['property' => $productId, 'associations' => ['customer', 'product']]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['customer', 'product'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('appends the associations element property after the configured associations by default')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $reviewResult = static::createStub(EntitySearchResult::class);
        $response = static::createStub(ProductReviewRouteResponse::class);
        $response->method('getResult')->willReturn($reviewResult);

        $this->productReviewRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductReviewRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $inputs = $this->resolve(
            new ProductReviewLoaderConfig(associations: ['customer']),
            ['productId' => 'product-alice', 'associations' => ['product', 'salesChannel']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['customer', 'product', 'salesChannel'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('returns notFound result when the product ID input is unresolved')]
    public function testLoadReturnsNotFoundWhenProductIdInputIsUnresolved(): void
    {
        $context = Generator::generateSalesChannelContext();

        $productReviewRoute = $this->createMock(AbstractProductReviewRoute::class);
        $productReviewRoute->expects($this->never())->method('load');

        $loader = new ProductReviewDataLoader($productReviewRoute);
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

    #[TestDox('returns notFound result when review route throws ProductException for review not active')]
    public function testLoadReturnsNotFoundWhenProductExceptionReviewNotActiveIsThrown(): void
    {
        $productId = 'test-product-id';

        $context = Generator::generateSalesChannelContext();

        $this->productReviewRoute
            ->method('load')
            ->willThrowException(new ProductException(403, 'PRODUCT__REVIEW_NOT_ACTIVE', 'Reviews not activated'));

        $result = $this->loader->load(
            new LoaderInputs(['property' => $productId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('returns notFound result when review route throws legacy ReviewNotActiveExeption')]
    public function testLoadReturnsNotFoundWhenLegacyReviewNotActiveExeptionIsThrown(): void
    {
        $productId = 'test-product-id';

        $context = Generator::generateSalesChannelContext();

        $this->productReviewRoute
            ->method('load')
            ->willThrowException(new ReviewNotActiveExeption());

        $result = $this->loader->load(
            new LoaderInputs(['property' => $productId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(ProductReviewLoaderConfig $config, array $properties): LoaderInputs
    {
        return (new LoaderInputResolver())->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('reviews', 'product_review', new ProductReviewLoaderConfig());
    }
}
