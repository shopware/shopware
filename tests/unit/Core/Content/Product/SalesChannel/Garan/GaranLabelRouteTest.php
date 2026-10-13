<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Extension\GaranLabelRouteExtension;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Garan\GaranLabelRoute;
use Shopware\Core\Content\Product\SalesChannel\Garan\GaranLabelRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\Test\Generator;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticSalesChannelRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelRoute::class)]
class GaranLabelRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository();

        $route = new GaranLabelRoute(
            $productRepository,
            static::createStub(GaranLabelResolver::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectExceptionObject(new DecorationPatternException(GaranLabelRoute::class));

        $route->getDecorated();
    }

    public function testLoadRendersLabelForCompleteProduct(): void
    {
        $productId = Uuid::randomHex();
        $product = $this->createProduct($productId, 'Acme', 'ACME-123', 36);

        $resolver = $this->createMock(GaranLabelResolver::class);
        $resolver->expects($this->exactly(2))
            ->method('resolve')
            ->with($product, static::logicalOr(static::equalTo(GaranLabelResolver::LABEL_TYPE_FULL), static::equalTo(GaranLabelResolver::LABEL_TYPE_NESTED)))
            ->willReturn('<svg>rendered</svg>');

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new ProductCollection([$product])]);

        $route = new GaranLabelRoute(
            $productRepository,
            $resolver,
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $response = $route->load($productId, Generator::generateSalesChannelContext());

        static::assertSame('<svg>rendered</svg>', $response->getObject()->get('svg'));
        static::assertSame('<svg>rendered</svg>', $response->getObject()->get('nestedSvg'));
    }

    public function testLoadThrowsWhenProductIsNotFound(): void
    {
        $productId = Uuid::randomHex();

        /** @var StaticSalesChannelRepository<ProductCollection> $productRepository */
        $productRepository = new StaticSalesChannelRepository([new ProductCollection()]);

        $route = new GaranLabelRoute(
            $productRepository,
            static::createStub(GaranLabelResolver::class),
            new ExtensionDispatcher(new EventDispatcher()),
        );

        $this->expectExceptionObject(ProductException::productNotFound($productId));

        $route->load($productId, Generator::generateSalesChannelContext());
    }

    public function testPublishesExtension(): void
    {
        $productId = Uuid::randomHex();
        $context = Generator::generateSalesChannelContext();
        $response = new GaranLabelRouteResponse('<svg>full</svg>', '<svg>nested</svg>');

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('garan-label-route.load.pre', static function (GaranLabelRouteExtension $extension) use ($productId, $context, $response): void {
            static::assertSame(['productId' => $productId, 'context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new GaranLabelRoute(
            static::createStub(SalesChannelRepository::class),
            static::createStub(GaranLabelResolver::class),
            new ExtensionDispatcher($dispatcher),
        );

        static::assertSame($response, $route->load($productId, $context));
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
