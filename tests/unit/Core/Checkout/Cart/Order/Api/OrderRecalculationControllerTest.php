<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\Order\Api;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Order\Api\OrderRecalculationController;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Api\Acl\AclAnnotationValidator;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Exception\MissingPrivilegeException;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
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
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('aclProtectedRouteProvider')]
    public function testOrderViewerIsRejected(string $routeName, array $expectedPrivileges): void
    {
        // an order viewer may read orders, but must not be able to change them
        $exception = $this->validate($this->loadRoute($routeName), ['order:read']);

        static::assertInstanceOf(MissingPrivilegeException::class, $exception, \sprintf('Route "%s" is not protected', $routeName));
        static::assertSame($expectedPrivileges, json_decode($exception->getMessage(), true)['missingPrivileges']);
    }

    /**
     * @param list<string> $expectedPrivileges
     */
    #[DataProvider('aclProtectedRouteProvider')]
    public function testPrivilegedUserIsAccepted(string $routeName, array $expectedPrivileges): void
    {
        $exception = $this->validate($this->loadRoute($routeName), $expectedPrivileges);

        static::assertNull($exception, \sprintf('Route "%s" rejected a user holding its own privileges', $routeName));
    }

    public function testEveryRouteIsAclProtected(): void
    {
        $routes = (new AttributeRouteControllerLoader())->load(OrderRecalculationController::class);

        foreach ($routes as $routeName => $route) {
            static::assertNotNull(
                $route->getDefault(PlatformRequest::ATTRIBUTE_ACL),
                \sprintf('Route "%s" mutates an order and must declare an ACL privilege', $routeName)
            );
        }
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

    /**
     * Runs the privileges of a real route through the real validator, without booting the kernel.
     *
     * @param list<string> $permissions
     */
    private function validate(Route $route, array $permissions): ?\Throwable
    {
        $source = new AdminApiSource(null, null);
        $source->setPermissions($permissions);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ACL, $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
        $request->attributes->set(
            PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
            new Context($source, [], Defaults::CURRENCY, [Defaults::LANGUAGE_SYSTEM])
        );

        $event = new ControllerEvent(
            static::createStub(HttpKernelInterface::class),
            static fn () => null,
            $request,
            HttpKernelInterface::MAIN_REQUEST
        );

        try {
            (new AclAnnotationValidator(static::createStub(Connection::class)))->validate($event);
        } catch (\Throwable $exception) {
            return $exception;
        }

        return null;
    }
}
