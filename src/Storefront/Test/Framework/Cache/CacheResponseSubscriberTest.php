<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\PlatformRequest;
use Shopware\Core\SalesChannelRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Shopware\Storefront\Framework\Routing\MaintenanceModeResolver;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @group cache
 */
class CacheResponseSubscriberTest extends TestCase
{
    use IntegrationTestBehaviour;

    private const IP = '127.0.0.1';

    public function testNoHeadersAreSetIfCacheIsDisabled(): void
    {
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            false,
            $this->getContainer()->get(MaintenanceModeResolver::class)
        );

        $customer = $this->createMock(CustomerEntity::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $response = new Response();
        $expectedHeaders = $response->headers->all();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $subscriber->setResponseCache($event);

        static::assertSame($expectedHeaders, $response->headers->all());
    }

    /**
     * @dataProvider cashHashProvider
     */
    public function testGenerateCashHashWithItemsInCart($customer, Cart $cart, bool $hasCookie): void
    {
        $service = $this->createMock(CartService::class);
        $service->method('getCart')->willReturn($cart);

        $subscriber = new CacheResponseSubscriber(
            $service,
            100,
            true,
            $this->getContainer()->get(MaintenanceModeResolver::class)
        );

        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $subscriber->setResponseCache($event);

        static::assertTrue($response->headers->has('set-cookie'));

        $cookies = array_filter($response->headers->getCookies(), function (Cookie $cookie) {
            return $cookie->getName() === CacheResponseSubscriber::CONTEXT_CACHE_COOKIE;
        });

        /** @var Cookie $cookie */
        static::assertCount(1, $cookies);
        $cookie = array_shift($cookies);

        if ($hasCookie) {
            static::assertNotNull($cookie->getValue());
        } else {
            static::assertNull($cookie->getValue());
        }
    }

    /**
     * @dataProvider maintenanceRequest
     */
    public function testMaintenanceRequest(bool $active, array $whitelist, bool $shouldBeCached): void
    {
        $cartService = $this->createMock(CartService::class);

        $subscriber = new CacheResponseSubscriber(
            $cartService,
            100,
            true,
            $this->getContainer()->get(MaintenanceModeResolver::class)
        );

        $customer = $this->createMock(CustomerEntity::class);
        $salesChannelContext = $this->createMock(SalesChannelContext::class);
        $salesChannelContext->method('getCustomer')->willReturn($customer);

        $request = new Request();
        $request->attributes->set(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT, $salesChannelContext);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE, $active);
        $request->attributes->set(SalesChannelRequest::ATTRIBUTE_SALES_CHANNEL_MAINTENANCE_IP_WHITLELIST, json_encode($whitelist));
        $request->server->set('REMOTE_ADDR', self::IP);

        static::assertSame(self::IP, $request->getClientIp());

        $response = new Response();

        $event = new ResponseEvent(
            $this->createMock(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MASTER_REQUEST,
            $response
        );

        $cart = new Cart('a', 'token');

        $count = $shouldBeCached ? 1 : 0;

        $cartService->expects(static::exactly($count))
            ->method('getCart')
            ->willReturn($cart);

        $requestStack = $this->getContainer()->get('request_stack');
        // ensure empty request stack
        while ($requestStack->pop()) {
        }

        $requestStack->push($request);

        $subscriber->setResponseCache($event);
    }

    public function cashHashProvider()
    {
        $emptyCart = new Cart('empty', 'empty');
        $customer = $this->createMock(CustomerEntity::class);

        $filledCart = new Cart('filled', 'filled');
        $filledCart->add(new LineItem('test', 'test', 'test'));

        yield 'Test with no logged in customer' => [null, $emptyCart, false];
        yield 'Test with logged in customer' => [$customer, $emptyCart, true];
        yield 'Test with filled cart' => [null, $filledCart, true];
        yield 'Test with filled cart and logged in customer' => [$customer, $filledCart, true];
    }

    public function maintenanceRequest()
    {
        yield 'Always cache requests when maintenance is inactive' => [false, [], true];
        yield 'Always cache requests when maintenance is active' => [true, [], true];
        yield 'Do not cache requests of whitelisted ip' => [true, [self::IP], false];
        yield 'Cache requests if ip is not whitelisted' => [true, ['120.0.0.0'], true];
    }
}
