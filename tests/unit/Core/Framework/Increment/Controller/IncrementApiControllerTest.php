<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Increment\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Increment\Controller\IncrementApiController;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\PlatformRequest;
use Symfony\Bundle\FrameworkBundle\Routing\AttributeRouteControllerLoader;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(IncrementApiController::class)]
class IncrementApiControllerTest extends TestCase
{
    #[DataProvider('aclProtectedRouteProvider')]
    public function testRouteRequiresIncrementManagePrivilege(string $routeName): void
    {
        $route = (new AttributeRouteControllerLoader())->load(IncrementApiController::class)->get($routeName);

        static::assertNotNull($route, \sprintf('Route "%s" is not defined on %s', $routeName, IncrementApiController::class));
        static::assertSame(['increment:manage'], $route->getDefault(PlatformRequest::ATTRIBUTE_ACL));
    }

    public static function aclProtectedRouteProvider(): \Generator
    {
        yield 'increment' => ['api.increment.increment'];
        yield 'decrement' => ['api.increment.decrement'];
        yield 'list' => ['api.increment.list'];
        yield 'reset' => ['api.increment.reset'];
        yield 'delete' => ['api.increment.delete'];
    }
}
