<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Api\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Api\OAuth\OAuthResource;
use Shopware\Core\Framework\Log\Package;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(OAuthResource::class)]
class OAuthResourceTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function testNormalize(string $resource, ?string $expected): void
    {
        static::assertSame($expected, OAuthResource::normalize($resource));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function normalizeProvider(): iterable
    {
        yield 'canonical' => ['https://shop.example/api/_mcp', 'https://shop.example/api/_mcp'];
        yield 'case of scheme and host' => ['HTTPS://Shop.Example/api/_mcp', 'https://shop.example/api/_mcp'];
        yield 'default https port' => ['https://shop.example:443/api/_mcp', 'https://shop.example/api/_mcp'];
        yield 'default http port' => ['http://shop.example:80/api/_mcp', 'http://shop.example/api/_mcp'];
        yield 'other port' => ['http://shop.example:8000/api/_mcp', 'http://shop.example:8000/api/_mcp'];
        yield 'trailing slash' => ['https://shop.example/api/_mcp/', 'https://shop.example/api/_mcp'];
        yield 'query' => ['https://shop.example/api/_mcp?toolsets=a', 'https://shop.example/api/_mcp'];
        yield 'path case is kept' => ['https://shop.example/API/_mcp', 'https://shop.example/API/_mcp'];
        yield 'fragment' => ['https://shop.example/api/_mcp#a', null];
        yield 'user info' => ['https://user:pass@shop.example/api/_mcp', null];
        yield 'other scheme' => ['ftp://shop.example/api/_mcp', null];
        yield 'relative' => ['/api/_mcp', null];
        yield 'empty' => ['', null];
        yield 'malformed' => ['https://', null];
    }
}
