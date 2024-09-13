<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRoute;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Category\Service\CategoryBreadcrumbBuilder;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(BreadcrumbRoute::class)]
class BreadcrumbRouteTest extends TestCase
{
    private BreadcrumbRoute $breadcrumbRoute;

    private MockObject&CategoryBreadcrumbBuilder $breadcrumbBuilder;

    private SalesChannelContext $context;

    protected function setUp(): void
    {
        $this->breadcrumbBuilder = $this->createMock(CategoryBreadcrumbBuilder::class);
        $this->context = $this->createMock(SalesChannelContext::class);

        $this->breadcrumbRoute = new BreadcrumbRoute(
            $this->breadcrumbBuilder
        );
    }

    public function testLoadCategoryBreadcrumbReturnsCorrectBreadcrumb(): void
    {
        $request = new Request(['id' => '1', 'type' => 'category']);
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn([new Breadcrumb('Home', '/')]);

        $response = $this->breadcrumbRoute->load($request, $this->context);
        $firstBreadcrumb = $response->getBreadcrumbCollection()->getBreadcrumb(0);

        static::assertCount(1, $response->getBreadcrumbCollection()->getBreadcrumbs());
        if ($firstBreadcrumb === null) {
            static::fail('Breadcrumb is null');
        }
        static::assertSame('Home', $firstBreadcrumb->name);
    }

    public function testGetDecoratedThrowsException(): void
    {
        $this->expectException(DecorationPatternException::class);
        $this->breadcrumbRoute->getDecorated();
    }

    public function testLoadProductBreadcrumbReturnsCorrectBreadcrumb(): void
    {
        $request = new Request(['id' => '1', 'type' => 'product']);
        $this->breadcrumbBuilder->method('getProductBreadcrumbUrls')->willReturn([new Breadcrumb('Product', 'product')]);

        $response = $this->breadcrumbRoute->load($request, $this->context);
        $firstBreadcrumb = $response->getBreadcrumbCollection()->getBreadcrumb(0);

        static::assertCount(1, $response->getBreadcrumbCollection()->getBreadcrumbs());
        if ($firstBreadcrumb === null) {
            static::fail('Breadcrumb is null');
        }
        static::assertSame('Product', $firstBreadcrumb->name);
    }

    public function testLoadProductBreadcrumbWithFallbackToCategory(): void
    {
        $request = new Request(['id' => '1', 'type' => 'product']);
        $this->breadcrumbBuilder->method('getProductBreadcrumbUrls')->willThrowException(new ProductNotFoundException('1'));
        $this->breadcrumbBuilder->method('getCategoryBreadcrumbUrls')->willReturn([new Breadcrumb('Category', 'category')]);

        $response = $this->breadcrumbRoute->load($request, $this->context);
        $firstBreadcrumb = $response->getBreadcrumbCollection()->getBreadcrumb(0);

        static::assertCount(1, $response->getBreadcrumbCollection()->getBreadcrumbs());
        if ($firstBreadcrumb === null) {
            static::fail('Breadcrumb is null');
        }
        static::assertSame('Category', $firstBreadcrumb->name);
    }

    public function testLoadBreadcrumbWithInvalidType(): void
    {
        $request = new Request(['id' => '1', 'type' => 'invalid']);
        $response = $this->breadcrumbRoute->load($request, $this->context);

        static::assertCount(0, $response->getBreadcrumbCollection()->getBreadcrumbs());
    }
}
