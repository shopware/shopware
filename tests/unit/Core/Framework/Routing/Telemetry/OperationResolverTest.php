<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Routing\Telemetry\OperationResolver;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OperationResolver::class)]
class OperationResolverTest extends TestCase
{
    #[DataProvider('routeProvider')]
    public function testResolve(string $route, string $expected): void
    {
        $request = Request::create('/');
        $request->attributes->set('_route', $route);

        static::assertSame($expected, (new OperationResolver())->resolve($request));
    }

    public function testResolveDefaultsToNoneWithoutRoute(): void
    {
        static::assertSame('none', (new OperationResolver())->resolve(Request::create('/')));
    }

    public static function routeProvider(): \Generator
    {
        yield 'clone is a write' => ['api.clone', 'write'];
        yield 'createVersion is a write' => ['api.createVersion', 'write'];
        yield 'mergeVersion is a write' => ['api.mergeVersion', 'write'];
        yield 'deleteVersion is a delete' => ['api.deleteVersion', 'delete'];

        yield 'list suffix is a read' => ['api.product.list', 'read'];
        yield 'detail suffix is a read' => ['api.product.detail', 'read'];
        yield 'search suffix is a read' => ['api.product.search', 'read'];
        yield 'search-ids suffix is a read' => ['api.product.search-ids', 'read'];
        yield 'aggregate suffix is a read' => ['api.product.aggregate', 'read'];

        yield 'create suffix is a write' => ['api.product.create', 'write'];
        yield 'update suffix is a write' => ['api.product.update', 'write'];
        yield 'delete suffix is a delete' => ['api.product.delete', 'delete'];

        yield 'unknown CRUD suffix is none' => ['api.product.unknown', 'none'];

        yield 'action API is none' => ['api.action.cache.index', 'none'];
        yield 'non-api route is none' => ['frontend.detail.page', 'none'];
        yield 'store-api route is none' => ['store-api.product.search', 'none'];
    }
}
