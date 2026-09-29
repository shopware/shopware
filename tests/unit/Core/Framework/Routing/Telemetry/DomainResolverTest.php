<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\DataAbstractionLayer\Telemetry\EntityGroupResolver;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Telemetry\DomainResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(DomainResolver::class)]
class DomainResolverTest extends TestCase
{
    /**
     * @param array<string, string> $attributes
     */
    #[DataProvider('routeProvider')]
    public function testResolve(string $route, array $attributes, string $expected): void
    {
        static::assertSame($expected, $this->createResolver()->resolve($this->createRequest($route, $attributes)));
    }

    public function testDoesNotMemoizeDynamicEntityRoutes(): void
    {
        $resolver = $this->createResolver();

        // clone/version routes share one fixed route name but carry a per-request `entity` param,
        // so they must be resolved fresh each call. Guards against a route-name memoization being extended
        // over these dynamic routes, which would pin every request to the first entity seen.
        static::assertSame('product', $resolver->resolve($this->createRequest('api.clone', ['entity' => 'product'])));
        static::assertSame('order', $resolver->resolve($this->createRequest('api.clone', ['entity' => 'order'])));
    }

    public static function routeProvider(): \Generator
    {
        // functional groups, first matching prefix wins
        yield 'frontend.detail maps to product' => ['frontend.detail.page', [], 'product'];
        yield 'store-api product maps to product' => ['store-api.product.list', [], 'product'];
        yield 'cart prefix precedes checkout' => ['frontend.checkout.cart', [], 'cart'];
        yield 'auth prefix precedes customer catch-all' => ['frontend.account.login.page', [], 'auth'];
        yield 'order prefix precedes customer catch-all' => ['frontend.account.order.page', [], 'order'];
        yield 'account catch-all maps to customer' => ['frontend.account.profile', [], 'customer'];
        yield 'unmatched frontend route is other' => ['frontend.unknown.page', [], 'other'];

        // action domains (segment after api.action.)
        yield 'action cache maps to cache' => ['api.action.cache.index', [], 'cache'];
        yield 'action index maps to indexing' => ['api.action.index', [], 'indexing'];
        yield 'action system-config maps to core' => ['api.action.system-config', [], 'core'];
        yield 'unknown action segment is other' => ['api.action.unknown.thing', [], 'other'];
        yield 'sync action is not treated as action domain' => ['api.action.sync', [], 'other'];

        // admin CRUD: entity from the entityName attribute, hyphens normalised to underscores
        yield 'admin CRUD uses entityName attribute' => ['api.product.detail', ['entityName' => 'product'], 'product'];
        yield 'admin CRUD normalises hyphenated resource name' => ['api.product-manufacturer.detail', ['entityName' => 'product-manufacturer'], 'product'];
        yield 'empty entityName falls through to other' => ['api.product.detail', ['entityName' => ''], 'other'];

        // clone / version specials: entity from the entity attribute
        yield 'clone uses entity attribute' => ['api.clone', ['entity' => 'product'], 'product'];
        yield 'createVersion uses entity attribute' => ['api.createVersion', ['entity' => 'order'], 'order'];
        yield 'version special without entity is other' => ['api.deleteVersion', [], 'other'];

        // unmatched admin route
        yield 'unmatched api route is other' => ['api.something.else', [], 'other'];
    }

    private function createResolver(): DomainResolver
    {
        return new DomainResolver(new EntityGroupResolver());
    }

    /**
     * @param array<string, string> $attributes
     */
    private function createRequest(string $route, array $attributes = []): Request
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);
        foreach ($attributes as $key => $value) {
            $request->attributes->set($key, $value);
        }

        return $request;
    }
}
