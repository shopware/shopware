<?php declare(strict_types=1);

namespace Shopware\Storefront\Test\Framework\Cache;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Customer\CustomerEntity;
use Shopware\Core\PlatformRequest;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Framework\Cache\CacheResponseSubscriber;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class CacheResponseSubscriberTest extends TestCase
{
    public function testNoHeadersAreSetIfCacheIsDisabled(): void
    {
        $subscriber = new CacheResponseSubscriber(
            $this->createMock(CartService::class),
            100,
            false
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

        $subscriber = new CacheResponseSubscriber($service, 100, true);

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
}
