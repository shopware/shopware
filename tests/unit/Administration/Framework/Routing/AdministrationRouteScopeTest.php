<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Administration\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Administration\Framework\Routing\AdministrationRouteScope;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(AdministrationRouteScope::class)]
class AdministrationRouteScopeTest extends TestCase
{
    private AdministrationRouteScope $routeScope;

    protected function setUp(): void
    {
        $this->routeScope = new AdministrationRouteScope();
    }

    public function testIsAllowed(): void
    {
        static::assertTrue($this->routeScope->isAllowed(new Request()));
    }

    public function testGetId(): void
    {
        static::assertSame(AdministrationRouteScope::ID, $this->routeScope->getId());
    }

    public function testAllowedPaths(): void
    {
        static::assertSame([AdministrationRouteScope::ALLOWED_PATH, ApiRouteScope::ID], $this->routeScope->getRoutePrefixes());
    }
}
