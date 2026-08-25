<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\ContentSystem\DataLoader;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductDetailDataLoader;
use Shopware\Core\Content\Product\ContentSystem\DataLoader\ProductDetailLoaderConfig;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Detail\AbstractProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\ContentSystem\Hydration\DataLoader\AbstractContentDataLoaderConfig;
use Shopware\Core\Framework\ContentSystem\Layout\Element\ContentElement;
use Shopware\Core\Framework\ContentSystem\Layout\Element\DataRequirement\DataRequirement;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\ContentSystem\ContentElementBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(ProductDetailDataLoader::class)]
class ProductDetailDataLoaderTest extends TestCase
{
    private AbstractProductDetailRoute&Stub $productDetailRoute;

    private ProductDetailDataLoader $loader;

    protected function setUp(): void
    {
        $this->productDetailRoute = static::createStub(AbstractProductDetailRoute::class);
        $this->loader = new ProductDetailDataLoader($this->productDetailRoute);
    }

    #[TestDox('returns product_detail as requirement type identifier')]
    public function testGetRequirementTypeReturnsProductDetailString(): void
    {
        static::assertSame('product_detail', ProductDetailDataLoader::getRequirementType());
    }

    #[TestDox('declares SalesChannelProductEntity as its single producible type')]
    public function testProducibleTypesDeclaresExtendsType(): void
    {
        $capabilities = $this->loader->producibleTypes();

        static::assertCount(1, $capabilities);
        static::assertSame(SalesChannelProductEntity::class, $capabilities[0]->producedType);
        static::assertSame([], $capabilities[0]->genericParameters);
        static::assertSame([], $capabilities[0]->configTemplate);
    }

    #[TestDox('returns the product as data and marks result as cache-aware with no tags')]
    public function testLoadReturnsCachedExternallyResultWithProduct(): void
    {
        $productId = Uuid::randomHex();
        $product = new SalesChannelProductEntity();
        $product->setUniqueIdentifier($productId);

        $config = new ProductDetailLoaderConfig();
        $requirement = new DataRequirement('product', 'product_detail', $config);
        $element = ContentElementBuilder::create('product-price')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();
        $request = new Request();

        $response = static::createStub(ProductDetailRouteResponse::class);
        $response->method('getProduct')->willReturn($product);

        $productDetailRoute = $this->createMock(AbstractProductDetailRoute::class);
        $productDetailRoute
            ->expects($this->once())
            ->method('load')
            ->with($productId, $request, $context, static::isInstanceOf(Criteria::class))
            ->willReturn($response);

        $loader = new ProductDetailDataLoader($productDetailRoute);
        $result = $loader->load($element, $requirement, $context, $request);

        static::assertSame($product, $result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[TestDox('lowercases productId before passing it to the product detail route')]
    public function testLoadCallsRouteWithLowercasedProductId(): void
    {
        $productId = Uuid::randomHex();
        $upperCaseId = strtoupper($productId);

        $config = new ProductDetailLoaderConfig();
        $requirement = new DataRequirement('product', 'product_detail', $config);
        $element = ContentElementBuilder::create('product-price')
            ->withProperty('productId', $upperCaseId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $response = static::createStub(ProductDetailRouteResponse::class);
        $response->method('getProduct')->willReturn(new SalesChannelProductEntity());

        $capturedProductId = null;
        $this->productDetailRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductDetailRouteResponse {
                $capturedProductId = $prodId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($productId, $capturedProductId);
    }

    #[TestDox('reads productId from custom property name when configured')]
    public function testLoadUsesCustomPropertyNameFromConfig(): void
    {
        $productId = Uuid::randomHex();

        $config = new ProductDetailLoaderConfig(property: 'pinnedProductId');
        $requirement = new DataRequirement('product', 'product_detail', $config);
        $element = ContentElementBuilder::create('product-price')
            ->withProperty('pinnedProductId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $capturedProductId = null;
        $response = static::createStub(ProductDetailRouteResponse::class);
        $response->method('getProduct')->willReturn(new SalesChannelProductEntity());

        $this->productDetailRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId) use (&$capturedProductId, $response): ProductDetailRouteResponse {
                $capturedProductId = $prodId;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertSame($productId, $capturedProductId);
    }

    #[TestDox('adds config associations to criteria when loading the product')]
    public function testLoadAddsConfigAssociationsToCriteria(): void
    {
        $productId = Uuid::randomHex();

        $config = new ProductDetailLoaderConfig(associations: ['manufacturer', 'media']);
        $requirement = new DataRequirement('product', 'product_detail', $config);
        $element = ContentElementBuilder::create('product-price')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $response = static::createStub(ProductDetailRouteResponse::class);
        $response->method('getProduct')->willReturn(new SalesChannelProductEntity());

        $this->productDetailRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductDetailRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
    }

    #[TestDox('merges element associations property into criteria when it is an array of strings')]
    public function testLoadMergesElementAssociationsIntoCriteria(): void
    {
        $productId = Uuid::randomHex();

        $config = new ProductDetailLoaderConfig(associations: ['manufacturer']);
        $requirement = new DataRequirement('product', 'product_detail', $config);
        $element = ContentElementBuilder::create('product-price')
            ->withProperty('productId', $productId)
            ->withProperty('associations', ['media', 'options'])
            ->build();
        $context = Generator::generateSalesChannelContext();

        /** @var Criteria|null $capturedCriteria */
        $capturedCriteria = null;
        $response = static::createStub(ProductDetailRouteResponse::class);
        $response->method('getProduct')->willReturn(new SalesChannelProductEntity());

        $this->productDetailRoute
            ->method('load')
            ->willReturnCallback(static function (string $prodId, Request $req, $ctx, Criteria $criteria) use (&$capturedCriteria, $response): ProductDetailRouteResponse {
                $capturedCriteria = $criteria;

                return $response;
            });

        $this->loader->load($element, $requirement, $context, new Request());

        static::assertInstanceOf(Criteria::class, $capturedCriteria);
        static::assertArrayHasKey('manufacturer', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('media', $capturedCriteria->getAssociations());
        static::assertArrayHasKey('options', $capturedCriteria->getAssociations());
    }

    #[TestDox('returns notFound result when config is not a ProductDetailLoaderConfig instance')]
    public function testLoadReturnsNotFoundWhenConfigIsWrongType(): void
    {
        $wrongConfig = static::createStub(AbstractContentDataLoaderConfig::class);
        $requirement = new DataRequirement('product', 'product_detail', $wrongConfig);
        $element = ContentElementBuilder::create('product-price')->build();
        $context = Generator::generateSalesChannelContext();

        $productDetailRoute = $this->createMock(AbstractProductDetailRoute::class);
        $productDetailRoute->expects($this->never())->method('load');

        $loader = new ProductDetailDataLoader($productDetailRoute);
        $result = $loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }

    #[DataProvider('guardsInvalidProductIdProvider')]
    #[TestDox('returns notFound result when productId is invalid: $_dataName')]
    public function testLoadReturnsNotFoundWhenProductIdPropertyIsInvalid(ContentElement $element): void
    {
        $config = new ProductDetailLoaderConfig();
        $context = Generator::generateSalesChannelContext();

        $productDetailRoute = $this->createMock(AbstractProductDetailRoute::class);
        $productDetailRoute->expects($this->never())->method('load');

        $loader = new ProductDetailDataLoader($productDetailRoute);
        $result = $loader->load(
            $element,
            new DataRequirement('product', 'product_detail', $config),
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
    public static function guardsInvalidProductIdProvider(): iterable
    {
        yield 'non-string value triggers guard' => [
            ContentElementBuilder::create('product-price')->withProperty('productId', 42)->build(),
        ];
        yield 'missing property triggers guard' => [
            ContentElementBuilder::create('product-price')->build(),
        ];
    }

    #[TestDox('returns notFound result when the product detail route throws a ProductException')]
    public function testLoadReturnsNotFoundWhenProductExceptionIsThrown(): void
    {
        $productId = Uuid::randomHex();

        $config = new ProductDetailLoaderConfig();
        $requirement = new DataRequirement('product', 'product_detail', $config);
        $element = ContentElementBuilder::create('product-price')
            ->withProperty('productId', $productId)
            ->build();
        $context = Generator::generateSalesChannelContext();

        $this->productDetailRoute
            ->method('load')
            ->willThrowException(ProductException::productNotFound($productId));

        $result = $this->loader->load($element, $requirement, $context, new Request());

        static::assertNull($result->data);
        static::assertTrue($result->isCacheAware());
        static::assertSame([], $result->getCacheTags());
    }
}
