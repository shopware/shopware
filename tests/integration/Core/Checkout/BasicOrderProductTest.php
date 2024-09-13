<?php declare(strict_types=1);

namespace Shopware\Tests\Integration\Core\Checkout;

use PHPUnit\Framework\TestCase;
use Shopware\Core\Content\Test\Product\ProductBuilder;
use Shopware\Core\Framework\Test\IdsCollection;
use Shopware\Core\Framework\Test\TestCaseBase\IntegrationTestBehaviour;
use Shopware\Core\Test\Integration\Helper\MailEventListener;
use Shopware\Core\Test\Integration\Traits\TestShortHands;

/**
 * @internal
 */
class BasicOrderProductTest extends TestCase
{
    use IntegrationTestBehaviour;
    use TestShortHands;

    public function testBasicOrderFlow(): void
    {
        $product = (new ProductBuilder(new IdsCollection(), 'p1'))
            ->stock(100)
            ->price(100)
            ->visibility();

        // the product builder has a helper function to write the product values to the database, including all dependencies (rules, currencies, properties, etc)
        $product->write($this->getContainer());

        $context = $this->getContext();
        $context = $this->login($context);

        // now we test that the product can be added to a customers cart
        $cart = $this->addProductToCart($product->id, $context);

        $this->assertLineItemInCart($cart, $product->id);

        $this->assertLineItemUnitPrice($cart, $product->id, 100);

        $this->assertLineItemTotalPrice($cart, $product->id, 100);

        $orderId = $this->mailListener(function (MailEventListener $listener) use ($cart, $context) {
            $orderId = $this->order($cart, $context);

            $listener->assertSent('order_confirmation_mail');

            return $orderId;
        });

        $item = $this->assertProductInOrder($orderId, $product->id);

        static::assertEquals(100, $item->getUnitPrice());

        static::assertEquals(100, $item->getTotalPrice());

        $this->assertStock($product->id, 99, 99);
    }
}
