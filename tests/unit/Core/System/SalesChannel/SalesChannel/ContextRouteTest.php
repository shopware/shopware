<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\System\SalesChannel\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Extensions\ExtensionDispatcher;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Extension\ContextRouteExtension;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextLoadRouteResponse;
use Shopware\Core\System\SalesChannel\SalesChannel\ContextRoute;
use Shopware\Core\Test\Generator;
use Symfony\Component\EventDispatcher\EventDispatcher;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(ContextRoute::class)]
class ContextRouteTest extends TestCase
{
    public function testGetDecoratedThrows(): void
    {
        static::expectExceptionObject(new DecorationPatternException(ContextRoute::class));

        (new ContextRoute(new ExtensionDispatcher(new EventDispatcher())))->getDecorated();
    }

    public function testLoadReturnsContextTokenHeader(): void
    {
        $context = Generator::generateSalesChannelContext(token: 'test-token');

        $response = (new ContextRoute(new ExtensionDispatcher(new EventDispatcher())))->load($context);

        static::assertSame($context, $response->getContext());
        static::assertSame('test-token', $response->headers->get(PlatformRequest::HEADER_CONTEXT_TOKEN));
    }

    public function testPublishesExtension(): void
    {
        $context = Generator::generateSalesChannelContext();
        $response = new ContextLoadRouteResponse($context);

        $dispatcher = new EventDispatcher();
        $dispatcher->addListener('context-route.load.pre', static function (ContextRouteExtension $extension) use ($context, $response): void {
            static::assertSame(['context' => $context], $extension->getParams());

            $extension->result = $response;
            $extension->stopPropagation();
        });

        $route = new ContextRoute(new ExtensionDispatcher($dispatcher));

        static::assertSame($response, $route->load($context));
    }
}
