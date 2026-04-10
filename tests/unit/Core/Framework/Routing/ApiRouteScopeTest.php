<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\Context\AdminApiSource;
use Shopware\Core\Framework\Api\Context\SystemSource;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Routing\ApiRouteScope;
use Shopware\Core\Framework\Routing\RoutingException;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(ApiRouteScope::class)]
class ApiRouteScopeTest extends TestCase
{
    private ApiRouteScope $scope;

    protected function setUp(): void
    {
        $this->scope = new ApiRouteScope();
    }

    #[DataProvider('allowsPathProvider')]
    #[TestDox('allows only paths under /api and /sw-domain-hash.html')]
    public function testIsAllowedPath(string $path, bool $expected): void
    {
        static::assertSame($expected, $this->scope->isAllowedPath($path));
    }

    /**
     * @param array<string, string|bool|Context> $attributes
     */
    #[DataProvider('allowsProvider')]
    #[TestDox('grants access only to requests carrying a valid Context with AdminApiSource')]
    public function testIsAllowed(array $attributes, bool $expected): void
    {
        static::assertSame($expected, $this->scope->isAllowed(new Request(attributes: $attributes)));
    }

    /**
     * @param array<string, string|bool> $attributes
     */
    #[DataProvider('throwsOnMissingContextProvider')]
    #[TestDox('throws RoutingException when the context attribute is absent or not a Context instance')]
    public function testIsAllowedThrowsOnMissingContext(array $attributes, string $routeAttribute, string $route): void
    {
        $this->expectExceptionObject(RoutingException::missingRouteAttribute($routeAttribute, $route));
        $this->scope->isAllowed(new Request(attributes: $attributes));
    }

    /**
     * @return \Generator<string, array{path: string, expected: bool}>
     */
    public static function allowsPathProvider(): \Generator
    {
        yield '/api route' => [
            'path' => '/api/v1/product',
            'expected' => true,
        ];

        yield '/sw-domain-hash.html' => [
            'path' => '/sw-domain-hash.html',
            'expected' => true,
        ];

        yield '/storefront route' => [
            'path' => '/storefront/product',
            'expected' => false,
        ];
    }

    /**
     * @return \Generator<string, array{attributes: array<string, string|bool>, routeAttribute: string, route: string}>
     */
    public static function throwsOnMissingContextProvider(): \Generator
    {
        yield 'no context attribute set' => [
            'attributes' => [],
            'routeAttribute' => PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
            'route' => '',
        ];

        yield 'context attribute is not a Context instance with route' => [
            'attributes' => [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => 'not-a-context', '_route' => 'api.product.list'],
            'routeAttribute' => PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT,
            'route' => 'api.product.list',
        ];
    }

    /**
     * @return \Generator<string, array{attributes: array<string, string|bool|Context>, expected: bool}>
     */
    public static function allowsProvider(): \Generator
    {
        yield 'AdminApiSource with auth required (default)' => [
            'attributes' => [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => new Context(new AdminApiSource(null))],
            'expected' => true,
        ];

        yield 'SystemSource with auth required (default)' => [
            'attributes' => [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => new Context(new SystemSource())],
            'expected' => false,
        ];

        yield 'SystemSource with auth_required=false' => [
            'attributes' => [PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT => new Context(new SystemSource()), 'auth_required' => false],
            'expected' => true,
        ];
    }
}
