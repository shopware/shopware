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
use Shopware\Core\Framework\Uuid\Uuid;
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
        $context = Generator::generateSalesChannelContext();

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
            Generator::generateSalesChannelContext()
        );

        static::assertSame('[]', $response->getContent());
    }

    public function testDoesNotCallTheRouteWithoutAValidProductId(): void
    {
        $route = $this->createMock(AbstractBreadcrumbRoute::class);
        $route->expects($this->never())->method('load');

        $response = $this->controller($route)->productCategories(
            new Request(['productId' => 'not-an-id']),
            Generator::generateSalesChannelContext()
        );

        static::assertSame('[]', $response->getContent());
    }

    private function controller(AbstractBreadcrumbRoute $route): AnalyticsController
    {
        $controller = new AnalyticsController($route);
        $controller->setContainer(new ContainerBuilder());

        return $controller;
    }
}
