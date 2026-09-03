<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\CrossSellingLoaderConfig;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\AbstractProductCrossSellingRoute;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\CrossSellingElementCollection;
use Shopware\Core\Content\Product\SalesChannel\CrossSelling\ProductCrossSellingRouteResponse;
use Shopware\Core\Content\ProductStream\ProductStreamException;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputResolver;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\LoaderInputs;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Script\ScriptException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(CrossSellingDataLoader::class)]
class CrossSellingDataLoaderTest extends TestCase
{
    private AbstractProductCrossSellingRoute&Stub $crossSellingRoute;

    private CrossSellingDataLoader $loader;

    protected function setUp(): void
    {
        $this->crossSellingRoute = static::createStub(AbstractProductCrossSellingRoute::class);
        $this->loader = new CrossSellingDataLoader($this->crossSellingRoute);
    }

    #[TestDox('returns cross_selling as requirement type identifier')]
    public function testGetRequirementTypeReturnsCrossSellingString(): void
    {
        static::assertSame('cross_selling', CrossSellingDataLoader::getRequirementType());
    }

    #[TestDox('declares CrossSellingElementCollection as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(CrossSellingElementCollection::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('returns cross-selling collection as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithCrossSellingData(): void
    {
        $productId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $crossSellingCollection = new CrossSellingElementCollection();
        $response = new ProductCrossSellingRouteResponse($crossSellingCollection);

        $crossSellingRoute = $this->createMock(AbstractProductCrossSellingRoute::class);
        $crossSellingRoute
            ->expects($this->once())
            ->method('load')
            ->with($productId, $request, $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $loader = new CrossSellingDataLoader($crossSellingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => $productId, 'associations' => []]),
            self::requirement(),
            $context,
            $request,
        );

        static::assertSame($crossSellingCollection, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases productId before passing it to the cross-selling route')]
    public function testLoadCallsCrossSellingRouteWithLowercasedProductId(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);

        $context = Generator::generateSalesChannelContext();

        $crossSellingCollection = new CrossSellingElementCollection();
        $response = new ProductCrossSellingRouteResponse($crossSellingCollection);

        $capturedProductId = null;
        $this->crossSellingRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductCrossSellingRouteResponse {
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
        $productId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        $capturedProductId = null;
        $crossSellingCollection = new CrossSellingElementCollection();
        $response = new ProductCrossSellingRouteResponse($crossSellingCollection);

        $this->crossSellingRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductCrossSellingRouteResponse {
                $capturedProductId = $prodId;

                return $response;
            });

        $inputs = $this->resolve(
            new CrossSellingLoaderConfig(property: 'mainProductId'),
            ['mainProductId' => $productId],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertSame($productId, $capturedProductId);
    }

    #[TestDox('resolves an unset property to the declared productId default')]
    public function testUnsetPropertyResolvesToDeclaredProductIdDefault(): void
    {
        $productId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        $crossSellingCollection = new CrossSellingElementCollection();
        $response = new ProductCrossSellingRouteResponse($crossSellingCollection);

        $crossSellingRoute = $this->createMock(AbstractProductCrossSellingRoute::class);
        $crossSellingRoute
            ->expects($this->once())
            ->method('load')
            ->with($productId, static::isInstanceOf(Request::class), $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $loader = new CrossSellingDataLoader($crossSellingRoute);
        $inputs = $this->resolve(new CrossSellingLoaderConfig(), ['productId' => $productId]);

        $loader->load($inputs, self::requirement(), $context, new Request());
    }

    #[TestDox('adds every configured association to the criteria')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $productId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $crossSellingCollection = new CrossSellingElementCollection();
        $response = new ProductCrossSellingRouteResponse($crossSellingCollection);

        $this->crossSellingRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductCrossSellingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load(
            new LoaderInputs(['property' => $productId, 'associations' => ['manufacturer', 'cover']]),
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
        $productId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $crossSellingCollection = new CrossSellingElementCollection();
        $response = new ProductCrossSellingRouteResponse($crossSellingCollection);

        $this->crossSellingRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductCrossSellingRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $inputs = $this->resolve(
            new CrossSellingLoaderConfig(associations: ['manufacturer']),
            ['productId' => $productId, 'associations' => ['cover', 'media']],
        );

        $this->loader->load($inputs, self::requirement(), $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertSame(['manufacturer', 'cover', 'media'], array_keys($capturedCriteria->getAssociations()));
    }

    #[TestDox('returns notFound result when the product ID input is unresolved')]
    public function testLoadReturnsNotFoundWhenProductIdInputIsUnresolved(): void
    {
        $context = Generator::generateSalesChannelContext();

        $crossSellingRoute = $this->createMock(AbstractProductCrossSellingRoute::class);
        $crossSellingRoute->expects($this->never())->method('load');

        $loader = new CrossSellingDataLoader($crossSellingRoute);
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

        $crossSellingRoute = $this->createMock(AbstractProductCrossSellingRoute::class);
        $crossSellingRoute->expects($this->never())->method('load');

        $loader = new CrossSellingDataLoader($crossSellingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => '{{productId}}', 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('sampleDomainExceptionProvider')]
    #[TestDox('degrades to notFound when the cross-selling route throws the Shopware exception $_dataName')]
    public function testLoadReturnsNotFoundWhenCrossSellingRouteThrows(\Throwable $exception): void
    {
        $productId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        $crossSellingRoute = $this->createMock(AbstractProductCrossSellingRoute::class);
        $crossSellingRoute
            ->expects($this->once())
            ->method('load')
            ->willThrowException($exception);

        $loader = new CrossSellingDataLoader($crossSellingRoute);
        $result = $loader->load(
            new LoaderInputs(['property' => $productId, 'associations' => []]),
            self::requirement(),
            $context,
            new Request(),
        );

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lets a TypeError from the cross-selling route propagate instead of degrading')]
    public function testLoadLetsThrowableOutsideShopwareHttpExceptionPropagate(): void
    {
        $productId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();

        $typeError = new \TypeError('Argument #1 ($productId) must be of type string, null given');

        $crossSellingRoute = static::createStub(AbstractProductCrossSellingRoute::class);
        $crossSellingRoute
            ->method('load')
            ->willThrowException($typeError);

        $loader = new CrossSellingDataLoader($crossSellingRoute);

        try {
            $loader->load(
                new LoaderInputs(['property' => $productId, 'associations' => []]),
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
     * Sample domain exceptions off the cross-selling chain, not one row per catch arm: the loader catches the
     * single covering ancestor `ShopwareHttpException`, so no row maps to a clause of its own.
     *
     * @return iterable<string, array{\Throwable}>
     */
    public static function sampleDomainExceptionProvider(): iterable
    {
        yield 'product stream not found' => [ProductStreamException::productStreamNotFound('stream-missing')];

        yield 'product stream has no filters' => [ProductStreamException::noFilters('stream-no-filters')];

        yield 'product stream is empty' => [ProductStreamException::emptyProductStream('stream-empty')];

        // AppScriptProductPriceCalculator decorates ProductPriceCalculator on the cross-selling chain, and
        // ScriptExecutor rewraps any Throwable an app script raises into ScriptExecutionFailedException, so
        // no enumeration of the chain's own exception classes can cover it.
        yield 'app script failure rewrapped as ScriptExecutionFailedException' => [
            ScriptException::scriptExecutionFailed('product-pricing', 'product-pricing.twig', new \RuntimeException('app script failed')),
        ];
    }

    /**
     * @param array<string, mixed> $properties
     */
    private function resolve(CrossSellingLoaderConfig $config, array $properties): LoaderInputs
    {
        return (new LoaderInputResolver())->resolve($this->loader->configSpecification(), $config, $properties);
    }

    private static function requirement(): DataRequirement
    {
        return new DataRequirement('cross-selling', 'cross_selling', new CrossSellingLoaderConfig());
    }
}
