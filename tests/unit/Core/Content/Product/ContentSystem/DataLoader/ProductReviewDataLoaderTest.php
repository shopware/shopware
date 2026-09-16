<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductReviewLoaderConfig;
use Shopware\Core\Content\Product\Exception\ReviewNotActiveExeption;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader;
use Shopware\Core\Content\Product\SalesChannel\Review\ProductReviewResult;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('after-sales')]
#[CoversClass(ProductReviewDataLoader::class)]
class ProductReviewDataLoaderTest extends TestCase
{
    private AbstractProductReviewLoader&Stub $productReviewLoader;

    private ProductReviewDataLoader $loader;

    protected function setUp(): void
    {
        $this->productReviewLoader = static::createStub(AbstractProductReviewLoader::class);
        $this->loader = new ProductReviewDataLoader($this->productReviewLoader);
    }

    #[TestDox('returns product_review as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductReviewString(): void
    {
        static::assertSame('product_review', ProductReviewDataLoader::getRequirementType());
    }

    #[TestDox('declares ProductReviewResult as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(ProductReviewResult::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('returns the review result as data and marks it cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithReviewData(): void
    {
        $productId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $reviewResult = static::createStub(ProductReviewResult::class);

        $productReviewLoader = $this->createMock(AbstractProductReviewLoader::class);
        $productReviewLoader
            ->expects($this->once())
            ->method('load')
            ->with($request, $context, $productId)
            ->willReturn($reviewResult);

        $loader = new ProductReviewDataLoader($productReviewLoader);
        $result = $loader->load(
            new LoaderInputs(['property' => $productId]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertSame($reviewResult, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases productId before passing it to the review loader')]
    public function testLoadCallsReviewLoaderWithLowercasedProductId(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);

        $context = Generator::generateSalesChannelContext();

        $capturedProductId = null;
        $this->productReviewLoader
            ->method('load')
            ->willReturnCallback(function (Request $request, SalesChannelContext $ctx, string $prodId) use (&$capturedProductId): ProductReviewResult {
                $capturedProductId = $prodId;

                return static::createStub(ProductReviewResult::class);
            });

        $this->loader->load(
            new LoaderInputs(['property' => $upperCaseId]),
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
        $productId = Uuid::randomHex();

        $capturedProductId = null;
        $this->productReviewLoader
            ->method('load')
            ->willReturnCallback(function (Request $request, SalesChannelContext $ctx, string $prodId) use (&$capturedProductId): ProductReviewResult {
                $capturedProductId = $prodId;

                return static::createStub(ProductReviewResult::class);
            });

        $inputs = $this->resolve(
            new ProductReviewLoaderConfig(property: 'reviewProductId'),
            ['reviewProductId' => $productId],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($productId, $capturedProductId);
    }

    #[TestDox('resolves an unset property to the declared productId default')]
    public function testUnsetPropertyResolvesToDeclaredProductIdDefault(): void
    {
        $context = Generator::generateSalesChannelContext();
        $productId = Uuid::randomHex();

        $reviewResult = static::createStub(ProductReviewResult::class);

        $productReviewLoader = $this->createMock(AbstractProductReviewLoader::class);
        $productReviewLoader
            ->expects($this->once())
            ->method('load')
            ->with(static::isInstanceOf(Request::class), $context, $productId)
            ->willReturn($reviewResult);

        $loader = new ProductReviewDataLoader($productReviewLoader);
        $inputs = $this->resolve(new ProductReviewLoaderConfig(), ['productId' => $productId]);

        $result = $loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($reviewResult, $result->data);
    }

    #[TestDox('returns notFound result when the product ID input is unresolved')]
    public function testLoadReturnsNotFoundWhenProductIdInputIsUnresolved(): void
    {
        $context = Generator::generateSalesChannelContext();

        $productReviewLoader = $this->createMock(AbstractProductReviewLoader::class);
        $productReviewLoader->expects($this->never())->method('load');

        $loader = new ProductReviewDataLoader($productReviewLoader);
        $result = $loader->load(
            new LoaderInputs(['property' => null]),
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

        $productReviewLoader = $this->createMock(AbstractProductReviewLoader::class);
        $productReviewLoader->expects($this->never())->method('load');

        $loader = new ProductReviewDataLoader($productReviewLoader);
        $result = $loader->load(
            new LoaderInputs(['property' => '{{productId}}']),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the review loader throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenReviewLoaderThrows(\Throwable $exception): void
    {
        $context = Generator::generateSalesChannelContext();

        $productReviewLoader = $this->createMock(AbstractProductReviewLoader::class);
        $productReviewLoader
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $loader = new ProductReviewDataLoader($productReviewLoader);
        $result = $loader->load(
            new LoaderInputs(['property' => Uuid::randomHex()]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the review loader propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $context = Generator::generateSalesChannelContext();

        $typeError = new \TypeError('Argument #1 ($productId) must be of type string, null given');

        $this->productReviewLoader
            ->method('load')
            ->willThrowException($typeError);

        try {
            $this->loader->load(
                new LoaderInputs(['property' => Uuid::randomHex()]),
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
     * Sample domain exceptions off the review chain, not one row per catch arm: the loader catches the single
     * covering ancestor `ShopwareHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        // ProductReviewLoader/route throws this when the sales channel has reviews switched off. ProductException
        // extends HttpException, which extends ShopwareHttpException.
        yield 'reviews switched off for the sales channel' => [
            new ProductException(403, 'PRODUCT__REVIEW_NOT_ACTIVE', 'Reviews not activated'),
        ];

        // The deprecated legacy class the review chain used to throw. It extends ShopwareHttpException directly
        // rather than through ProductException, so a clause narrowed to one branch of that line would let it escape.
        yield 'the deprecated legacy ReviewNotActiveExeption' => [new ReviewNotActiveExeption()];

        // Not a reachability claim: this row pins the clause to the ancestor rather than to the chain's own
        // classes, using a class the review chain does not produce.
        yield 'a class outside the chain that extends ShopwareHttpException directly' => [
            new DecorationPatternException(AbstractProductReviewLoader::class),
        ];
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
