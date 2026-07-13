<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Garan\GaranLabelDurationFormatter;
use Shopware\Core\Content\Product\Garan\GaranLabelRenderer;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Garan\GaranLabelRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;

/**
 * @internal
 */
#[CoversClass(GaranLabelRoute::class)]
class GaranLabelRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository();

        $route = new GaranLabelRoute(
            $productRepository,
            new GaranLabelDurationFormatter(),
            static::createStub(GaranLabelRenderer::class),
        );

        $this->expectExceptionObject(new DecorationPatternException(GaranLabelRoute::class));

        $route->getDecorated();
    }

    public function testLoadRendersLabelForCompleteProduct(): void
    {
        $productId = Uuid::randomHex();
        $product = $this->createProduct($productId, 'Acme', 'ACME-123', 36);

        $renderer = $this->createMock(GaranLabelRenderer::class);
        $renderer->expects($this->once())
            ->method('render')
            ->with('3', 'Acme', 'ACME-123')
            ->willReturn('<svg>rendered</svg>');

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new ProductCollection([$product])]);

        $route = new GaranLabelRoute(
            $productRepository,
            new GaranLabelDurationFormatter(),
            $renderer,
        );

        $response = $route->load($productId, Generator::generateSalesChannelContext());

        static::assertSame('<svg>rendered</svg>', $response->getObject()->get('svg'));
    }

    public function testLoadReturnsNullSvgWhenManufacturerIsMissing(): void
    {
        $productId = Uuid::randomHex();
        $product = new SalesChannelProductEntity();
        $product->setId($productId);
        $product->setProductNumber('ACME-123');
        $product->setGuaranteeMonths(36);

        $renderer = $this->createMock(GaranLabelRenderer::class);
        $renderer->expects($this->never())->method('render');

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new ProductCollection([$product])]);

        $route = new GaranLabelRoute(
            $productRepository,
            new GaranLabelDurationFormatter(),
            $renderer,
        );

        $response = $route->load($productId, Generator::generateSalesChannelContext());

        static::assertNull($response->getObject()->get('svg'));
    }

    public function testLoadReturnsNullSvgWhenGuaranteeMonthsIsInvalid(): void
    {
        $productId = Uuid::randomHex();
        $product = $this->createProduct($productId, 'Acme', 'ACME-123', 12);

        $renderer = $this->createMock(GaranLabelRenderer::class);
        $renderer->expects($this->never())->method('render');

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new ProductCollection([$product])]);

        $route = new GaranLabelRoute(
            $productRepository,
            new GaranLabelDurationFormatter(),
            $renderer,
        );

        $response = $route->load($productId, Generator::generateSalesChannelContext());

        static::assertNull($response->getObject()->get('svg'));
    }

    public function testLoadThrowsWhenProductIsNotFound(): void
    {
        $productId = Uuid::randomHex();

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new ProductCollection()]);

        $route = new GaranLabelRoute(
            $productRepository,
            new GaranLabelDurationFormatter(),
            static::createStub(GaranLabelRenderer::class),
        );

        $this->expectExceptionObject(ProductException::productNotFound($productId));

        $route->load($productId, Generator::generateSalesChannelContext());
    }

    private function createProduct(string $id, string $manufacturer, string $productNumber, int $guaranteeMonths): SalesChannelProductEntity
    {
        $manufacturerEntity = new ProductManufacturerEntity();
        $manufacturerEntity->setId(Uuid::randomHex());
        $manufacturerEntity->setName($manufacturer);

        $product = new SalesChannelProductEntity();
        $product->setId($id);
        $product->setProductNumber($productNumber);
        $product->setManufacturer($manufacturerEntity);
        $product->setGuaranteeMonths($guaranteeMonths);

        return $product;
    }
}
