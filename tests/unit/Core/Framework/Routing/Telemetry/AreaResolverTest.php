<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\Framework\Routing\Telemetry\AreaResolver;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(AreaResolver::class)]
class AreaResolverTest extends TestCase
{
    /**
     * @param list<string> $scopes
     */
    #[DataProvider('requestProvider')]
    public function testResolve(string $route, array $scopes, string $expected): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, $scopes);

        static::assertSame($expected, (new AreaResolver())->resolve($request));
    }

    public function testResolveDefaultsToOtherWithoutRouteOrScope(): void
    {
        static::assertSame('other', (new AreaResolver())->resolve(Request::create('/')));
    }

    public static function requestProvider(): \Generator
    {
        yield 'sync route is special-cased regardless of scope' => ['api.action.sync', [ApiRouteScope::ID], 'sync-api'];
        yield 'payment finalize route is special-cased without scope' => ['payment.finalize.transaction', [], 'payment'];

        yield 'store-api scope' => ['store-api.product.search', [StoreApiRouteScope::ID], 'store-api'];
        yield 'admin-api scope' => ['api.product.search', [ApiRouteScope::ID], 'admin-api'];
        yield 'storefront scope' => ['frontend.detail.page', ['storefront'], 'storefront'];
        yield 'administration scope' => ['administration.index', ['administration'], 'administration'];
        yield 'admin dashboard route with administration scope' => ['api.admin.dashboard.order-amount', ['administration'], 'administration'];
        yield 'unknown scope is other' => ['some.route', ['unknown'], 'other'];

        // store-api takes precedence when several scopes are present
        yield 'store-api scope precedes admin-api' => ['mixed.route', [ApiRouteScope::ID, StoreApiRouteScope::ID], 'store-api'];
    }
}
