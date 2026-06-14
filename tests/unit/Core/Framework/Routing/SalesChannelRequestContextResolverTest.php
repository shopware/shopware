<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Routing;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Framework\Routing\RequestContextResolverInterface;
use Shopware\Core\Framework\Routing\RouteScopeRegistry;
use Shopware\Core\Framework\Routing\SalesChannelRequestContextResolver;
use Shopware\Core\Framework\Routing\StoreApiRouteScope;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceParameters;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\Test\TestDefaults;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(SalesChannelRequestContextResolver::class)]
class SalesChannelRequestContextResolverTest extends TestCase
{
    public function testEmptyLanguageAndCurrencyHeadersAreIgnored(): void
    {
        $context = $this->createMock(SalesChannelContext::class);
        $contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $contextService
            ->expects($this->once())
            ->method('get')
            ->willReturnCallback(static function (SalesChannelContextServiceParameters $parameters) use ($context): SalesChannelContext {
                static::assertSame(TestDefaults::SALES_CHANNEL, $parameters->getSalesChannelId());
                static::assertSame('test-token', $parameters->getToken());
                static::assertNull($parameters->getLanguageId());
                static::assertNull($parameters->getOverwriteCurrencyId());

                return $context;
            });

        $decorated = $this->createMock(RequestContextResolverInterface::class);
        $decorated
            ->expects($this->never())
            ->method('resolve');

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_ID, TestDefaults::SALES_CHANNEL);
        $request->attributes->set(PlatformRequest::ATTRIBUTE_ROUTE_SCOPE, [StoreApiRouteScope::ID]);
        $request->headers->set(PlatformRequest::HEADER_CONTEXT_TOKEN, 'test-token');
        $request->headers->set(PlatformRequest::HEADER_LANGUAGE_ID, '');
        $request->headers->set(PlatformRequest::HEADER_CURRENCY_ID, '');

        $resolver = new SalesChannelRequestContextResolver(
            $decorated,
            $contextService,
            new EventDispatcher(),
            new RouteScopeRegistry([new StoreApiRouteScope()])
        );

        $resolver->resolve($request);
    }
}
