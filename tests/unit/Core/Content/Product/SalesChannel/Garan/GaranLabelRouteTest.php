<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Garan;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Product\Aggregate\ProductManufacturer\ProductManufacturerEntity;
use Shopware\Core\Content\Product\Garan\GaranLabelResolver;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Content\Product\ProductException;
use Shopware\Core\Content\Product\SalesChannel\Garan\GaranLabelRoute;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(GaranLabelRoute::class)]
class GaranLabelRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        $route = new GaranLabelRoute(
            $this->createMock(SalesChannelRepository::class),
            static::createStub(GaranLabelResolver::class),
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

        $context = Generator::generateSalesChannelContext();

        $route = new GaranLabelRoute(
            $this->createProductRepository(new ProductCollection([$product]), $context),
            $resolver,
        );

        $response = $route->load($productId, $context);

        static::assertSame('<svg>rendered</svg>', $response->getObject()->get('svg'));
        static::assertSame('<svg>rendered</svg>', $response->getObject()->get('nestedSvg'));
    }

    public function testLoadThrowsWhenProductIsNotFound(): void
    {
        $productId = Uuid::randomHex();

        $context = Generator::generateSalesChannelContext();

        $route = new GaranLabelRoute(
            $this->createProductRepository(new ProductCollection(), $context),
            static::createStub(GaranLabelResolver::class),
        );

        $this->expectExceptionObject(ProductException::notFound($productId));

        $route->load($productId, $context);
    }

    private function createProductRepository(ProductCollection $products, SalesChannelContext $context): SalesChannelRepository
    {
        $repository = $this->createMock(SalesChannelRepository::class);
        $repository->method('search')->willReturn(
            new EntitySearchResult(
                ProductDefinition::ENTITY_NAME,
                $products->count(),
                $products,
                null,
                new Criteria(),
                $context->getContext()
            )
        );

        return $repository;
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
