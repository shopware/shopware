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

    public function testReturnsEmptyForAnEmptyValue(): void
    {
        static::assertSame([], $this->resolve('/api/_mcp?toolsets='));
    }

    public function testReturnsEmptyForABlankValue(): void
    {
        static::assertSame([], $this->resolve('/api/_mcp?toolsets=%20'));
    }

    /**
     * A non-scalar value must be ignored like any other unusable input. Reading it through
     * InputBag::get() would instead throw a BadRequestException and fail the whole tools/list.
     */
    public function testReturnsEmptyForAListValue(): void
    {
        static::assertSame([], $this->resolve('/api/_mcp?toolsets[]=entity&toolsets[]=order'));
    }

    public function testReturnsEmptyForAMapValue(): void
    {
        static::assertSame([], $this->resolve('/api/_mcp?toolsets[first]=entity'));
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

    public function testPassesUnknownNamesThrough(): void
    {
        static::assertSame(['all', 'does-not-exist'], $this->resolve('/api/_mcp?toolsets=all,does-not-exist'));
    }

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
