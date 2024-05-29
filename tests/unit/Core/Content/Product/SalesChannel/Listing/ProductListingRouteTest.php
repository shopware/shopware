<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Stub\DataAbstractionLayer\StaticEntityRepository;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ProductListingRoute::class)]
class ProductListingRouteTest extends TestCase
{
    public function testFiltersAreSetForCategories(): void
    {
        $categoryId = 'categoryId';
        $categoryRepository = new StaticEntityRepository([
            new EntityCollection([
                new PartialEntity([
                    'id' => $categoryId,
                    'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
                ]),
            ]),
        ]);

        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $categoryRepository,
            $this->createMock(ProductStreamBuilderInterface::class),
            new EventDispatcher()
        );

        $criteria = new Criteria();
        $controller->load($categoryId, new Request(), $this->createMock(SalesChannelContext::class), $criteria);

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.categoriesRo.id',
        ], $criteria->getFilterFields());
    }

    public function testFiltersAreSetForProductStreams(): void
    {
        $categoryId = 'categoryId';
        $streamId = 'streamId';
        $categoryRepository = new StaticEntityRepository([new EntityCollection([
            new PartialEntity(
                [
                    'id' => $categoryId,
                    'productStreamId' => $streamId,
                    'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
                ]
            )])]);

        $productStreamBuilder = $this->createMock(ProductStreamBuilderInterface::class);
        $productStreamBuilder->method('buildFilters')
            ->willReturn([new EqualsFilter('product.product_stream', $streamId)]);

        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $categoryRepository,
            $productStreamBuilder,
            new EventDispatcher()
        );

        $criteria = new Criteria();
        $result = $controller->load(
            $categoryId,
            new Request(),
            $this->createMock(SalesChannelContext::class),
            $criteria
        )->getResult();

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.product_stream',
        ], $criteria->getFilterFields());

        static::assertSame($streamId, $result->getStreamId());
    }

    public function testClassIsBaseOfDecorationChain(): void
    {
        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(ProductStreamBuilderInterface::class),
            new EventDispatcher()
        );

        $this->expectException(DecorationPatternException::class);

        $controller->getDecorated();
    }
}
