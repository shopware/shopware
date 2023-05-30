<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Test\Cart\LineItem\Group;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\LineItem\Group\AbstractProductLineItemProvider;
use Shopware\Core\Checkout\Cart\LineItem\Group\ProductLineItemProvider;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItem\LineItemCollection;
use Shopware\Core\Checkout\Test\Cart\LineItem\Group\Helpers\Traits\LineItemTestFixtureBehaviour;
use Shopware\Core\Framework\Log\Package;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\Framework\Uuid\Uuid;

/**
 * @internal
 */
#[Package('checkout')]
class ProductLineItemProviderTest extends TestCase
{
    use LineItemTestFixtureBehaviour;

    private AbstractProductLineItemProvider $provider;

    protected function setUp(): void
    {
        $this->provider = new ProductLineItemProvider();
    }

    public function testIsMatchingReturnProductLineItem(): void
    {
        $cart = $this->getCart();

        static::assertEquals(4, $cart->getLineItems()->count());

        $lineItems = $this->provider->getProducts($cart);

        static::assertEquals(1, $lineItems->count());
        static::assertEquals(LineItem::PRODUCT_LINE_ITEM_TYPE, $lineItems->first()->getType());
    }

    public function testItThrowsDecorationPatternException(): void
    {
        $this->expectException(DecorationPatternException::class);

        $this->provider->getDecorated();
    }

    private function getCart(): Cart
    {
        $items = [
            new LineItem(Uuid::randomHex(), LineItem::PRODUCT_LINE_ITEM_TYPE),
            new LineItem(Uuid::randomHex(), LineItem::PROMOTION_LINE_ITEM_TYPE),
            new LineItem(Uuid::randomHex(), LineItem::CREDIT_LINE_ITEM_TYPE),
            new LineItem(Uuid::randomHex(), LineItem::CUSTOM_LINE_ITEM_TYPE),
        ];

        $cart = new Cart('token');
        $cart->addLineItems(new LineItemCollection($items));

        return $cart;
    }
}
