<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Content\Product\Cart\Struct\CalculatedProduct;
use Shopware\Core\Checkout\Cart\Rule\CartRuleScope;
use Shopware\Core\Framework\Struct\StructCollection;

class ProductPostValidator implements CartProcessorInterface
{
    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        CheckoutContext $context
    ): void {
        $products = $calculatedCart->getCalculatedLineItems()->filterInstance(
            CalculatedProduct::class
        );

        if ($products->count() <= 0) {
            return;
        }

        /** @var CalculatedProduct[] $products */
        foreach ($products as $product) {
            if (!$product->getRule()) {
                continue;
            }

            $valid = $product->getRule()->match(new CartRuleScope($calculatedCart, $context));

            if ($valid) {
                continue;
            }

            $calculatedCart->getCalculatedLineItems()->remove($product->getIdentifier());
            $cart->getLineItems()->remove($product->getIdentifier());
        }
    }
}
