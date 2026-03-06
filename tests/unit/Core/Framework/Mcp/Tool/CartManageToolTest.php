<?php declare(strict_types=1);

namespace Shopware\Tests\Unit\Core\Framework\Mcp\Tool;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Cart\Price\Struct\CalculatedPrice;
use Shopware\Core\Checkout\Cart\Price\Struct\CartPrice;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Checkout\Cart\Tax\Struct\CalculatedTaxCollection;
use Shopware\Core\Checkout\Cart\Tax\Struct\TaxRuleCollection;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Mcp\Tool\CartManageTool;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Context\SalesChannelContextServiceInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

/**
 * @internal
 */
#[Package('framework')]
#[CoversClass(CartManageTool::class)]
class CartManageToolTest extends TestCase
{
    private SalesChannelContextServiceInterface $contextService;

    private CartService&MockObject $cartService;

    private SalesChannelContext $salesChannelContext;

    public function testCreateReturnsToken(): void
    {
        $tool = $this->createTool();

        $this->cartService->expects($this->once())->method('createNew');

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'create',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertNotEmpty($data['data']['token']);
        static::assertSame([], $data['data']['lineItems']);
        static::assertEqualsWithDelta(0.0, $data['data']['totalPrice'], 0.001);
        static::assertSame(0, $data['data']['itemCount']);
    }

    public function testAddReturnsFormattedCart(): void
    {
        $tool = $this->createTool();
        $productId = Uuid::randomHex();

        $cart = $this->createCartWithItems([
            ['id' => $productId, 'label' => 'Blue T-Shirt', 'quantity' => 2, 'unitPrice' => 29.99, 'totalPrice' => 59.98],
        ], 59.98, 50.40, 9.58);

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('add')->willReturn($cart);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'add',
            token: 'test-token',
            productId: $productId,
            quantity: 2,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame('test-token', $data['data']['token']);
        static::assertCount(1, $data['data']['lineItems']);
        static::assertSame('Blue T-Shirt', $data['data']['lineItems'][0]['label']);
        static::assertSame(2, $data['data']['lineItems'][0]['quantity']);
        static::assertEqualsWithDelta(59.98, $data['data']['totalPrice'], 0.001);
        static::assertSame(1, $data['data']['itemCount']);
    }

    public function testRemoveReturnsFormattedCart(): void
    {
        $tool = $this->createTool();

        $cart = $this->createCartWithItems([], 0.0, 0.0, 0.0);
        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('remove')->willReturn($cart);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'remove',
            token: 'test-token',
            lineItemId: 'some-item-id',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame(0, $data['data']['itemCount']);
    }

    public function testUpdateReturnsFormattedCart(): void
    {
        $tool = $this->createTool();
        $productId = Uuid::randomHex();

        $cart = $this->createCartWithItems([
            ['id' => $productId, 'label' => 'Blue T-Shirt', 'quantity' => 5, 'unitPrice' => 29.99, 'totalPrice' => 149.95],
        ], 149.95, 126.01, 23.94);

        $this->cartService->method('getCart')->willReturn($cart);
        $this->cartService->method('changeQuantity')->willReturn($cart);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'update',
            token: 'test-token',
            lineItemId: $productId,
            quantity: 5,
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame(5, $data['data']['lineItems'][0]['quantity']);
    }

    public function testGetReturnsCurrentCartState(): void
    {
        $tool = $this->createTool();

        $cart = $this->createCartWithItems([], 0.0, 0.0, 0.0);
        $this->cartService->method('getCart')->willReturn($cart);

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'get',
            token: 'test-token',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertTrue($data['success']);
        static::assertSame('test-token', $data['data']['token']);
    }

    public function testMissingTokenReturnsError(): void
    {
        $tool = $this->createTool();

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'add',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Token is required', $data['error']);
    }

    public function testMissingProductIdForAddReturnsError(): void
    {
        $tool = $this->createTool();

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'add',
            token: 'test-token',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('productId is required', $data['error']);
    }

    public function testInvalidActionReturnsError(): void
    {
        $tool = $this->createTool();

        $output = ($tool)(
            salesChannelId: Uuid::randomHex(),
            action: 'invalid',
        );

        $data = json_decode($output, true, 512, \JSON_THROW_ON_ERROR);
        static::assertFalse($data['success']);
        static::assertStringContainsString('Invalid action', $data['error']);
    }

    private function createTool(): CartManageTool
    {
        $this->salesChannelContext = $this->createMock(SalesChannelContext::class);

        $this->contextService = $this->createMock(SalesChannelContextServiceInterface::class);
        $this->contextService->method('get')->willReturn($this->salesChannelContext);

        $this->cartService = $this->createMock(CartService::class);

        return new CartManageTool($this->contextService, $this->cartService);
    }

    /**
     * @param list<array{id: string, label: string, quantity: int, unitPrice: float, totalPrice: float}> $items
     */
    private function createCartWithItems(array $items, float $totalPrice, float $netPrice, float $taxAmount): Cart
    {
        $lineItems = [];
        foreach ($items as $item) {
            $lineItem = new LineItem($item['id'], LineItem::PRODUCT_LINE_ITEM_TYPE, $item['id'], $item['quantity']);
            $lineItem->setLabel($item['label']);
            $lineItem->setPrice(new CalculatedPrice(
                $item['unitPrice'],
                $item['totalPrice'],
                new CalculatedTaxCollection(),
                new TaxRuleCollection(),
                $item['quantity'],
            ));
            $lineItems[] = $lineItem;
        }

        $cart = new Cart('test-token');
        $cart->setLineItems(new LineItemCollection($lineItems));

        $taxCollection = new CalculatedTaxCollection();
        $cart->setPrice(new CartPrice(
            $netPrice,
            $totalPrice,
            $totalPrice,
            $taxCollection,
            new TaxRuleCollection(),
            CartPrice::TAX_STATE_GROSS,
        ));

        return $cart;
    }
}
