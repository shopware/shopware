<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRoute;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Category\SalesChannel\CategoryRoute;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Adapter\Cache\CacheTagCollector;
use Shopware\Core\Framework\DataAbstractionLayer\Cache\EntityCacheKeyGenerator;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('inventory')]
#[CoversClass(BreadcrumbRoute::class)]
class BreadcrumbRouteTest extends TestCase
{
    private BreadcrumbRoute $breadcrumbRoute;

    private Stub&CategoryBreadcrumbBuilder $breadcrumbBuilder;

    private CacheTagCollector&Stub $cacheTagCollector;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->breadcrumbBuilder = static::createStub(CategoryBreadcrumbBuilder::class);
        $this->cacheTagCollector = static::createStub(CacheTagCollector::class);
        $this->context = static::createStub(SalesChannelContext::class);

        $this->breadcrumbRoute = $this->createRoute();
    }

    public function testLoadCategoryBreadcrumbReturnsCorrectBreadcrumb(): void
    {
        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId('019192b9cd82711482744d7b456b6c01');
        $categoryEntity->setName('Test LP');
        $categoryEntity->setType('category');

        $request = new Request(['type' => 'category'], [], ['id' => '1']);
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Home', 'categoryId1')]));
        $this->breadcrumbBuilder->method('loadCategory')->willReturn($categoryEntity);

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(CategoryRoute::buildName('categoryId1'));

        $collection = $this->createRoute($cacheTagCollector)->load($request, $this->context)->getBreadcrumbCollection();
        static::assertCount(1, $collection);
        $firstBreadcrumb = $collection->first();
        static::assertNotNull($firstBreadcrumb);

        static::assertSame('Home', $firstBreadcrumb->name);
    }

    public function testLoadCategoryBreadcrumbReturnsCorrectBreadcrumbNullCategory(): void
    {
        $request = new Request(['type' => 'category'], [], ['id' => '1']);
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Home', 'categoryId1')]));

        $response = $this->breadcrumbRoute->load($request, $this->context);
        static::assertNull($response->getBreadcrumbCollection()->first());
    }

    public function testGetDecoratedThrowsException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->breadcrumbRoute->getDecorated();
    }

    public function testLoadProductBreadcrumbReturnsCorrectBreadcrumb(): void
    {
        $request = new Request(['type' => 'product'], [], ['id' => 'productId1']);
        $this->breadcrumbBuilder->method('getProductBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Product', 'categoryId1')]));

        $cacheTagCollector = $this->createMock(CacheTagCollector::class);
        $cacheTagCollector
            ->expects($this->once())
            ->method('addTag')
            ->with(
                CategoryRoute::buildName('categoryId1'),
                EntityCacheKeyGenerator::buildProductTag('productId1')
            );

        $collection = $this->createRoute($cacheTagCollector)->load($request, $this->context)->getBreadcrumbCollection();
        static::assertCount(1, $collection);
        $firstBreadcrumb = $collection->first();
        static::assertNotNull($firstBreadcrumb);

        static::assertSame('Product', $firstBreadcrumb->name);
    }

    public function testLoadProductBreadcrumbWithFallbackToCategory(): void
    {
        $categoryEntity = new CategoryEntity();
        $categoryEntity->setId('019192b9cd82711482744d7b456b6c01');
        $categoryEntity->setName('Test LP');
        $categoryEntity->setType('page');

        $request = new Request(['type' => 'product'], [], ['id' => '1']);
        $this->breadcrumbBuilder->method('getProductBreadcrumbUrls')->willThrowException(new ProductNotFoundException('1'));
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Category', 'category')]));
        $this->breadcrumbBuilder->method('loadCategory')->willReturn($categoryEntity);

        $collection = $this->breadcrumbRoute->load($request, $this->context)->getBreadcrumbCollection();
        static::assertCount(1, $collection);
        $firstBreadcrumb = $collection->first();
        static::assertNotNull($firstBreadcrumb);
        static::assertSame('Category', $firstBreadcrumb->name);
    }

    public function testLoadProductBreadcrumbWithFallbackToCategoryNullCategory(): void
    {
        $request = new Request(['type' => 'product'], [], ['id' => '1']);
        $this->breadcrumbBuilder->method('getProductBreadcrumbUrls')->willThrowException(new ProductNotFoundException('1'));
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn(new BreadcrumbCollection([new Breadcrumb('Category', 'category')]));

        $response = $this->breadcrumbRoute->load($request, $this->context);
        static::assertNull($response->getBreadcrumbCollection()->first());
    }

    public function testLoadBreadcrumbWithInvalidType(): void
    {
        $request = new Request(['type' => 'invalid'], [], ['id' => '1']);
        $response = $this->breadcrumbRoute->load($request, $this->context);

        static::assertCount(0, $response->getBreadcrumbCollection());
    }

    private function createRoute(?CacheTagCollector $cacheTagCollector = null): BreadcrumbRoute
    {
        return new BreadcrumbRoute(
            $this->breadcrumbBuilder,
            $cacheTagCollector ?? $this->cacheTagCollector,
        );
    }
}
