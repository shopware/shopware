<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Listing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\CategoryDefinition;
use Shopware\Core\Content\Product\Extension\ProductListingCriteriaExtension;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingLoader;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingRoute;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\DataAbstractionLayer\EntityCollection;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\PartialEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\TestCaseHelper\CallableClass;
use Shopware\Core\Framework\Uuid\Uuid;
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
        /** @var StaticEntityRepository<EntityCollection<PartialEntity>> */
        $categoryRepository = new StaticEntityRepository([
            new EntityCollection([
                new PartialEntity([
                    'id' => $categoryId,
                    'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
                ]),
            ]),
        ]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(ProductListingRoute::buildName($categoryId));

        $productStreamBuilder = $this->createMock(AbstractProductStreamBuilder::class);
        $productStreamBuilder->expects($this->never())->method('enrichCriteria');

        $eventDispatcher = new EventDispatcher();
        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $categoryRepository,
            $productStreamBuilder,
            $cacheTagCollector,
            new ExtensionDispatcher($eventDispatcher),
        );

        $criteria = new Criteria();
        $controller->load($categoryId, new Request(), $this->createSalesChannelContextMock(), $criteria);

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
        $streamId = Uuid::randomHex();
        /** @var StaticEntityRepository<EntityCollection<PartialEntity>> */
        $categoryRepository = new StaticEntityRepository([
            new EntityCollection([
                new PartialEntity(
                    [
                        'id' => $categoryId,
                        'productStreamId' => $streamId,
                        'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
                    ]
                ),
            ]),
        ]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(
                ProductListingRoute::buildName($categoryId),
                'product-stream-' . $streamId
            );

        $context = Context::createDefaultContext();
        $productStreamBuilder = $this->createMock(AbstractProductStreamBuilder::class);
        $productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), $streamId, $context)
            ->willReturnCallback(static function (Criteria $criteria, string $id, mixed ...$_): void {
                $criteria->addFilter(new EqualsFilter('product.stock', 10));
            });

        $eventDispatcher = new EventDispatcher();
        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $categoryRepository,
            $productStreamBuilder,
            $cacheTagCollector,
            new ExtensionDispatcher($eventDispatcher),
        );

        $criteria = new Criteria();
        $result = $controller->load(
            $categoryId,
            new Request(),
            $this->createSalesChannelContextMock($context),
            $criteria
        )->getResult();

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.stock',
        ], $criteria->getFilterFields());

        static::assertSame($streamId, $result->getStreamId());
    }

    public function testFiltersAndTagsAreSetForDescendantCategories(): void
    {
        $categoryId = 'parent-category';
        $childCategoryId = 'child-category';
        $streamId = Uuid::randomHex();

        /** @var StaticEntityRepository<EntityCollection<PartialEntity>> */
        $categoryRepository = new StaticEntityRepository([
            new EntityCollection([
                new PartialEntity([
                    'id' => $categoryId,
                    'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
                ]),
                new PartialEntity([
                    'id' => $childCategoryId,
                    'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
                    'productStreamId' => $streamId,
                ]),
            ]),
        ]);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector->expects($this->once())
            ->method('addTag')
            ->with(
                ProductListingRoute::buildName($categoryId),
                ProductListingRoute::buildName($childCategoryId),
                'product-stream-' . $streamId
            );

        $context = Context::createDefaultContext();
        $productStreamBuilder = $this->createMock(AbstractProductStreamBuilder::class);
        $productStreamBuilder->expects($this->once())
            ->method('enrichCriteria')
            ->with(static::isInstanceOf(Criteria::class), $streamId, $context)
            ->willReturnCallback(static function (Criteria $criteria, string $id, mixed ...$_): void {
                $criteria->addFilter(new EqualsFilter('product.stock', 10));
            });

        $eventDispatcher = new EventDispatcher();
        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $categoryRepository,
            $productStreamBuilder,
            $cacheTagCollector,
            new ExtensionDispatcher($eventDispatcher),
        );

        $criteria = new Criteria();
        $controller->load($categoryId, new Request(), $this->createSalesChannelContextMock($context), $criteria);

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.stock',
            'product.categoriesRo.id',
        ], $criteria->getFilterFields());
    }

    public function testClassIsBaseOfDecorationChain(): void
    {
        $eventDispatcher = new EventDispatcher();
        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $this->createMock(EntityRepository::class),
            $this->createMock(AbstractProductStreamBuilder::class),
            $this->createMock(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
        );

        $this->expectException(DecorationPatternException::class);

        $controller->getDecorated();
    }

    public function testExtension(): void
    {
        $categoryId = 'categoryId';
        /** @var StaticEntityRepository<EntityCollection<PartialEntity>> */
        $categoryRepository = new StaticEntityRepository([
            new EntityCollection([
                new PartialEntity([
                    'id' => $categoryId,
                    'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT,
                ]),
            ]),
        ]);

        $eventDispatcher = new EventDispatcher();

        $listener = $this->createMock(CallableClass::class);
        $listener->expects($this->exactly(2))->method('__invoke');
        $eventDispatcher->addListener(ProductListingCriteriaExtension::NAME . '.pre', $listener);
        $eventDispatcher->addListener(ProductListingCriteriaExtension::NAME . '.post', $listener);

        $productStreamBuilder = $this->createMock(AbstractProductStreamBuilder::class);
        $productStreamBuilder->expects($this->never())->method('enrichCriteria');

        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $categoryRepository,
            $productStreamBuilder,
            $this->createMock(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
        );

        $criteria = new Criteria();
        $controller->load($categoryId, new Request(), $this->createSalesChannelContextMock(), $criteria);

        static::assertSame([
            'product.visibilities.visibility',
            'product.visibilities.salesChannelId',
            'product.active',
            'product.categoriesRo.id',
        ], $criteria->getFilterFields());
    }

    public function testProductStreamWithDisplayAsGroupFalseCanEnableDirectVariantState(): void
    {
        $categoryId = 'categoryId';
        $streamId = Uuid::randomHex();

        /** @var StaticEntityRepository<EntityCollection<PartialEntity>> */
        $categoryRepository = new StaticEntityRepository([
            new EntityCollection([
                new PartialEntity(
                    [
                        'id' => $categoryId,
                        'productStreamId' => $streamId,
                        'productAssignmentType' => CategoryDefinition::PRODUCT_ASSIGNMENT_TYPE_PRODUCT_STREAM,
                    ]
                ),
            ]),
        ]);

        $productStreamBuilder = $this->createMock(AbstractProductStreamBuilder::class);
        $productStreamBuilder->method('enrichCriteria')
            ->willReturnCallback(static function (Criteria $criteria, string $id, mixed ...$_): void {
                $criteria->addFilter(new EqualsFilter('product.product_stream', $id));
                $criteria->addState(AbstractProductStreamBuilder::STATE_DISPLAY_AS_GROUP_DISABLED);
            });

        $eventDispatcher = new EventDispatcher();
        $controller = new ProductListingRoute(
            $this->createMock(ProductListingLoader::class),
            $categoryRepository,
            $productStreamBuilder,
            $this->createMock(CacheTagCollector::class),
            new ExtensionDispatcher($eventDispatcher),
        );

        $criteria = new Criteria();
        $controller->load(
            $categoryId,
            new Request(),
            $this->createSalesChannelContextMock(),
            $criteria
        );

        static::assertTrue($criteria->hasState(AbstractProductStreamBuilder::STATE_DISPLAY_AS_GROUP_DISABLED));
    }

    /**
     * @return SalesChannelContext&MockObject
     */
    private function createSalesChannelContextMock(?Context $innerContext = null): SalesChannelContext
    {
        $context = $this->createMock(SalesChannelContext::class);
        $context->method('getContext')->willReturn($innerContext ?? Context::createDefaultContext());
        $context->method('getSalesChannelId')->willReturn('sales-channel-id');

        return $context;
    }
}
