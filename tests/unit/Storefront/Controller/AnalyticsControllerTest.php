<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Storefront\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\BreadcrumbException;
use Shopware\Core\Content\Breadcrumb\SalesChannel\AbstractBreadcrumbRoute;
use Shopware\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRouteResponse;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\ShopwareHttpException;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\Generator;
use Shopware\Storefront\Controller\AnalyticsController;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('discovery')]
#[CoversClass(AnalyticsController::class)]
class AnalyticsControllerTest extends TestCase
{
    public function testReturnsTheBreadcrumbNamesOfTheProduct(): void
    {
        $productId = Uuid::randomHex();
        $context = $this->contextWithAnalytics();

        $route = $this->createMock(AbstractBreadcrumbRoute::class);
        $route->expects($this->once())
            ->method('load')
            ->with(
                static::callback(static fn (Request $request) => $request->attributes->get('id') === $productId
                    && $request->query->get('type') === 'product'),
                $context
            )
            ->willReturn(new BreadcrumbRouteResponse(new BreadcrumbCollection([
                new Breadcrumb('Damen', Uuid::randomHex()),
                new Breadcrumb('Schuhe', Uuid::randomHex()),
            ])));

        $response = $this->controller($route)->productCategories(new Request(['productId' => $productId]), $context);

        static::assertSame('["Damen","Schuhe"]', $response->getContent());
    }

    public function testReturnsNoCategoriesForAProductWithoutBreadcrumb(): void
    {
        $route = static::createStub(AbstractBreadcrumbRoute::class);
        $route->method('load')->willThrowException(BreadcrumbException::categoryNotFoundForProduct(Uuid::randomHex()));

        $response = $this->controller($route)->productCategories(
            new Request(['productId' => Uuid::randomHex()]),
            $this->contextWithAnalytics()
        );

        static::assertSame(200, $response->getStatusCode());
        static::assertSame('[]', $response->getContent());
    }

    public function testLetsOtherErrorsThrough(): void
    {
        $route = static::createStub(AbstractBreadcrumbRoute::class);
        $route->method('load')->willThrowException(BreadcrumbException::categoryNotFound(Uuid::randomHex()));

        $this->expectException(ShopwareHttpException::class);

        $this->controller($route)->productCategories(new Request(['productId' => Uuid::randomHex()]), $this->contextWithAnalytics());
    }

    public function testAnswers404ForASalesChannelWithoutAnalytics(): void
    {
        $route = $this->createMock(AbstractBreadcrumbRoute::class);
        $route->expects($this->never())->method('load');

        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setAnalyticsId(null);

        $response = $this->controller($route)->productCategories(new Request(['productId' => Uuid::randomHex()]), $context);

        static::assertSame(404, $response->getStatusCode());
    }

    public function testDoesNotCallTheRouteWithoutAValidProductId(): void
    {
        $route = $this->createMock(AbstractBreadcrumbRoute::class);
        $route->expects($this->never())->method('load');

        $response = $this->controller($route)->productCategories(
            new Request(['productId' => 'not-an-id']),
            $this->contextWithAnalytics()
        );

        static::assertSame(400, $response->getStatusCode());
    }

    private function contextWithAnalytics(): SalesChannelContext
    {
        $context = Generator::generateSalesChannelContext();
        $context->getSalesChannel()->setAnalyticsId(Uuid::randomHex());

        return $context;
    }

    private function controller(AbstractBreadcrumbRoute $route): AnalyticsController
    {
        $controller = new AnalyticsController($route);
        $controller->setContainer(new ContainerBuilder());

        return $controller;
    }
}
