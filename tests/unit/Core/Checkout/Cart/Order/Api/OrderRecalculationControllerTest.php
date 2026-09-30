<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Api;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\Api\OrderRecalculationController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\Routing\Route;

/**
 * @internal
 */
#[Package('checkout')]
#[CoversClass(OrderRecalculationController::class)]
class OrderRecalculationControllerTest extends TestCase
{
    /**
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteDeclaresPrivilege(string $routeName, array $expectedPrivileges): void
    {
        static::assertSame($expectedPrivileges, $this->loadRoute($routeName)->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    /**
     * @return \Generator<string, array{0: string, 1: list<string>}>
     */
    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'recalculating an order' => ['api.action.order.recalculate', ['order:update']];
        yield 'adding a product' => ['api.action.order.add-product', ['order:update']];
        yield 'adding a credit item' => ['api.action.order.add-credit-item', ['order:update']];
        yield 'adding a custom line item' => ['api.action.order.add-line-item', ['order:update']];
        yield 'adding a promotion item' => ['api.action.order.add-promotion-item', ['order:update']];
        yield 'toggling automatic promotions' => ['api.action.order.toggle-automatic-promotions', ['order:update']];
        yield 'applying automatic promotions' => ['api.action.order.apply-automatic-promotions', ['order:update']];
        yield 'replacing an order address' => ['api.action.order.replace-order-address', ['order_address:update']];
        yield 'updating the order addresses' => ['api.action.order.update', ['order_address:update']];
    }

    private function loadRoute(string $routeName): Route
    {
        $route = (new AttributeRouteControllerLoader())->load(OrderRecalculationController::class)->get($routeName);

        static::assertNotNull($route, \sprintf('Route "%s" is not defined on %s', $routeName, OrderRecalculationController::class));

        return $route;
    }
}
