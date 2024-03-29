<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Checkout\Cart\SalesChannel;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\AbstractCartPersister;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartCalculator;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryRegistry;
use Shopware\Core\Checkout\Cart\SalesChannel\CartItemAddRoute;
use Shopware\Core\Framework\RateLimiter\RateLimiter;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * @internal
 */
#[CoversClass(CartItemAddRoute::class)]
class CartItemAddRouteTest extends TestCase
{
    public function testRateLimitationWithoutIp(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute(null);

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'quantity' => 2,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item, null),
            new Cart('test'),
            $this->createMock(SalesChannelContext::class),
            null
        );
    }

    public function testRateLimitationId(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute('line-item-id-127.0.0.1');

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'quantity' => 2,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item),
            new Cart(Uuid::randomHex()),
            $this->createMock(SalesChannelContext::class),
            null
        );
    }

    public function testRateLimitationReferenceId(): void
    {
        $cartItemAddRoute = $this->createCartItemAddRoute('line-item-referenced-id-127.0.0.1');

        $item = [
            'id' => 'line-item-id',
            'type' => 'line-item-type',
            'referencedId' => 'line-item-referenced-id',
            'quantity' => 2,
        ];

        $cartItemAddRoute->add(
            $this->createRequest($item),
            new Cart(Uuid::randomHex()),
            $this->createMock(SalesChannelContext::class),
            null
        );
    }

    private function createCartItemAddRoute(?string $expectedCacheKey): CartItemAddRoute
    {
        $rateLimiter = $this->createMock(RateLimiter::class);
        $rateLimiter
            ->expects(static::exactly($expectedCacheKey === null ? 0 : 1))
            ->method('ensureAccepted')
            ->willReturnCallback(function (string $route, string $key) use ($expectedCacheKey): void {
                static::assertSame($route, RateLimiter::CART_ADD_LINE_ITEM);
                static::assertSame($expectedCacheKey, $key);
            });

        $lineItemFactory = $this->createMock(LineItemFactoryRegistry::class);
        $lineItemFactory
            ->expects(static::atLeastOnce())
            ->method('create')
            ->willReturnCallback(
                fn ($dataBag): LineItem => new LineItem($dataBag['id'], $dataBag['type'], $dataBag['referencedId'] ?? null, $dataBag['quantity'])
            );

        return new CartItemAddRoute(
            $this->createMock(CartCalculator::class),
            $this->createMock(AbstractCartPersister::class),
            $this->createMock(EventDispatcherInterface::class),
            $lineItemFactory,
            $rateLimiter
        );
    }

    /**
     * @param array<string, string|int> $lineItems
     */
    private function createRequest(array $lineItems, ?string $ip = '127.0.0.1'): Request
    {
        $items = [
            'items' => [
                $lineItems,
            ],
        ];

        if ($ip === null) {
            return new Request([], $items);
        }

        return new Request([], $items, [], [], [], ['REMOTE_ADDR' => $ip]);
    }
}
