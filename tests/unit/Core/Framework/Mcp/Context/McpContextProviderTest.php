<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Context;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Context\McpContextProvider;
use Shopware\Core\PlatformRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(McpContextProvider::class)]
class McpContextProviderTest extends TestCase
{
    public function testReturnsContextFromRequest(): void
    {
        $context = Context::createDefaultContext();

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_CONTEXT_OBJECT, $context);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new McpContextProvider($requestStack);

        static::assertSame($context, $provider->getContext());
    }

    public function testReturnsCLIContextWhenNoRequest(): void
    {
        $requestStack = new RequestStack();

        $provider = new McpContextProvider($requestStack);
        $context = $provider->getContext();

        static::assertSame(Context::createCLIContext()->getSource()::class, $context->getSource()::class);
    }

    public function testReturnsCLIContextWhenNoContextOnRequest(): void
    {
        $request = new Request();

        $requestStack = new RequestStack();
        $requestStack->push($request);

        $provider = new McpContextProvider($requestStack);
        $context = $provider->getContext();

        static::assertSame(Context::createCLIContext()->getSource()::class, $context->getSource()::class);
    }
}
