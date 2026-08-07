<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\Api\OrderConverterController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Route;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderConverterController::class)]
class OrderConverterControllerTest extends TestCase
{
    public function testConvertToCartRouteDeclaresOrderReadPrivilege(): void
    {
        static::assertSame(['order:read'], $this->loadConvertToCartRoute()->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    private function loadConvertToCartRoute(): Route
    {
        $route = (new AttributeRouteControllerLoader())->load(OrderConverterController::class)->get('api.action.order.convert-to-cart');

        static::assertNotNull($route, \sprintf('Route "api.action.order.convert-to-cart" is not defined on %s', OrderConverterController::class));

        return $route;
    }
}
