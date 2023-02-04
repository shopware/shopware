<?php
declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Product\SalesChannel\Detail;

use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Cms\SalesChannel\SalesChannelCmsPageLoader;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductConfiguratorLoader;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute;
use Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRouteResponse;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\ProductCloseoutFilterFactory;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductDefinition;
use Shopware\Core\Content\Product\SalesChannel\SalesChannelProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\EntitySearchResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\IdSearchResult;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 *
 * @covers \Shopware\Core\Content\Product\SalesChannel\Detail\ProductDetailRoute
 */
class ProductDetailRouteTest extends TestCase
{
    /**
     * @var MockObject&SalesChannelRepository
     */
    private SalesChannelRepository $productRepository;

    /**
     * @var MockObject&SystemConfigService
     */
    private SystemConfigService $systemConfig;

    private ProductDetailRoute $route;

    /**
     * @var MockObject&SalesChannelContext
     */
    private SalesChannelContext $context;

    private IdsCollection $idsCollection;

    private AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory;

    public function setUp(): void
    {
        parent::setUp();
        $this->context = $this->createMock(SalesChannelContext::class);
        $this->idsCollection = new IdsCollection();
        $this->productRepository = $this->createMock(SalesChannelRepository::class);
        $this->systemConfig = $this->createMock(SystemConfigService::class);
        $configuratorLoader = $this->createMock(ProductConfiguratorLoader::class);
        $breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $cmsPageLoader = $this->createMock(SalesChannelCmsPageLoader::class);
        $this->productCloseoutFilterFactory = new ProductCloseoutFilterFactory();

        $this->route = new ProductDetailRoute(
            $this->productRepository,
            $this->systemConfig,
            $configuratorLoader,
            $breadcrumbBuilder,
            $cmsPageLoader,
            new SalesChannelProductDefinition(),
            $this->productCloseoutFilterFactory
        );
    }

    public function testLoadMainVariant(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('mainVariant');
        $this->productRepository->expects(static::exactly(2))
            ->method('search')
            ->willReturn(
                new EntitySearchResult(
                    'product',
                    1,
                    new ProductCollection([$productEntity]),
                    null,
                    new Criteria(),
                    $this->context->getContext()
                )
            );

        $result = $this->route->load('1', new Request(), $this->context, new Criteria());

        static::assertInstanceOf(ProductDetailRouteResponse::class, $result);
        static::assertEquals('4', $result->getProduct()->getCmsPageId());
        static::assertEquals('mainVariant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testLoadBestVariant(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setCmsPageId('4');
        $productEntity->setId($this->idsCollection->create('product1'));
        $productEntity->setUniqueIdentifier('BestVariant');

        $idsSearchResult = new IdSearchResult(
            1,
            [
                [
                    'primaryKey' => $this->idsCollection->get('product1'),
                    'data' => [],
                ],
            ],
            new Criteria(),
            $this->context->getContext()
        );
        $this->productRepository->method('searchIds')
            ->willReturn(
                $idsSearchResult
            );
        $this->productRepository->expects(static::exactly(2))
            ->method('search')
            ->willReturnOnConsecutiveCalls(
                (new EntitySearchResult('product', 0, new ProductCollection(), null, new Criteria(), $this->context->getContext())),
                (new EntitySearchResult('product', 4, new ProductCollection([$productEntity]), null, new Criteria(), $this->context->getContext()))
            );

        $result = $this->route->load($this->idsCollection->get('product1'), new Request(), $this->context, new Criteria());

        static::assertInstanceOf(ProductDetailRouteResponse::class, $result);
        static::assertEquals(4, $result->getProduct()->getCmsPageId());
        static::assertEquals('BestVariant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testConfigHideCloseoutProductsWhenOutOfStockFiltersResults(): void
    {
        $productEntity = new SalesChannelProductEntity();
        $productEntity->setCmsPageId('4');
        $productEntity->setUniqueIdentifier('BestVariant');

        $criteria = new Criteria([$this->idsCollection->get('product2')]);

        $criteria2 = new Criteria([$this->idsCollection->get('product2')]);
        $criteria2->setTitle('product-detail-route');
        $criteria2->addFilter(
            new ProductAvailableFilter('', ProductVisibilityDefinition::VISIBILITY_LINK)
        );

        $filter = $this->productCloseoutFilterFactory->create($this->context);
        $filter->addQuery(new EqualsFilter('product.parentId', null));
        $criteria2->addFilter($filter);

        $this->productRepository
            ->expects(static::exactly(2))
            ->method('search')
            ->withConsecutive(
                [
                    $criteria,
                    $this->context,
                ],
                [
                    $criteria2,
                    $this->context,
                ]
            )->willReturnOnConsecutiveCalls(
                new EntitySearchResult('product', 0, new ProductCollection([]), null, new Criteria(), $this->context->getContext()),
                new EntitySearchResult('product', 4, new ProductCollection([$productEntity]), null, new Criteria(), $this->context->getContext())
            );

        $this->systemConfig->method('get')->willReturn(true);

        $result = $this->route->load($this->idsCollection->get('product2'), new Request(), $this->context, new Criteria());

        static::assertInstanceOf(ProductDetailRouteResponse::class, $result);
        static::assertEquals('4', $result->getProduct()->getCmsPageId());
        static::assertEquals('BestVariant', $result->getProduct()->getUniqueIdentifier());
    }

    public function testLoadProductNotFound(): void
    {
        $this->expectException(ProductNotFoundException::class);

        $this->route->load('1', new Request(), $this->context, new Criteria());
    }

    public function testGetDecorated(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->route->getDecorated();
    }
}
