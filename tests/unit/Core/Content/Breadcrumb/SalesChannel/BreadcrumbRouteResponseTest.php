<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Content\Breadcrumb\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Breadcrumb\SalesChannel\BreadcrumbRouteResponse;
use Shopware\Core\Content\Breadcrumb\Struct\Breadcrumb;
use Shopware\Core\Content\Breadcrumb\Struct\BreadcrumbCollection;

/**
 * @internal
 */
#[CoversClass(BreadcrumbRouteResponse::class)]
class BreadcrumbRouteResponseTest extends TestCase
{
    public function testBreadcrumbIsCorrectlyConstructed(): void
    {
        $breadcrumb1 = new Breadcrumb('Home', '/');
        $breadcrumb2 = new Breadcrumb('Products', '/products');
        $breadcrumb3 = new Breadcrumb('Home', '/products/electronics');

        $breadcrumbData = [
            $breadcrumb1,
            $breadcrumb2,
            $breadcrumb3,
        ];

        $breadcrumb = new BreadcrumbCollection($breadcrumbData);
        $breadcrumbRouteResponse = new BreadcrumbRouteResponse($breadcrumb);

        static::assertSame($breadcrumb, $breadcrumbRouteResponse->getBreadcrumbCollection());
    }
}
