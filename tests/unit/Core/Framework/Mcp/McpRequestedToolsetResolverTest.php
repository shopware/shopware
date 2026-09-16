<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\McpRequestedToolsetResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpRequestedToolsetResolver::class)]
class McpRequestedToolsetResolverTest extends TestCase
{
    public function testReturnsEmptyWithoutARequest(): void
    {
        static::assertSame([], (new McpRequestedToolsetResolver(new RequestStack()))->resolve());
    }

    public function testReturnsEmptyWithoutTheQueryParameter(): void
    {
        static::assertSame([], $this->resolve('/api/_mcp'));
    }

    public function testReturnsEmptyForABlankValue(): void
    {
        static::assertSame([], $this->resolve('/api/_mcp?toolsets=%20'));
    }

    public function testResolvesASingleToolset(): void
    {
        static::assertSame(['entity'], $this->resolve('/api/_mcp?toolsets=entity'));
    }

    public function testResolvesACommaSeparatedList(): void
    {
        static::assertSame(['entity', 'order'], $this->resolve('/api/_mcp?toolsets=entity,order'));
    }

    public function testTrimsWhitespaceAndDropsEmptySegments(): void
    {
        static::assertSame(['entity', 'order'], $this->resolve('/api/_mcp?toolsets=%20entity%20,,%20order%20,'));
    }

    public function testDeduplicatesNames(): void
    {
        static::assertSame(['entity', 'order'], $this->resolve('/api/_mcp?toolsets=entity,order,entity'));
    }

    /**
     * Names are passed through verbatim. Validation and the `all` shorthand belong to the registry,
     * which is the only place that knows which toolsets exist.
     */
    public function testPassesUnknownNamesThrough(): void
    {
        static::assertSame(['all', 'does-not-exist'], $this->resolve('/api/_mcp?toolsets=all,does-not-exist'));
    }

    /**
     * App scripts run as internal subrequests without the client's query string, so the selection
     * must be read off the main request.
     */
    public function testResolvesOffTheMainRequestNotASubrequest(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/api/_mcp?toolsets=entity', 'POST'));
        $stack->push(Request::create('/api/script/some-hook', 'POST'));

        static::assertSame(['entity'], (new McpRequestedToolsetResolver($stack))->resolve());
    }

    /**
     * @return list<string>
     */
    private function resolve(string $uri): array
    {
        $stack = new RequestStack();
        $stack->push(Request::create($uri, 'POST'));

        return (new McpRequestedToolsetResolver($stack))->resolve();
    }
}
