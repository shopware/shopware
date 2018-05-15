<?php declare(strict_types=1);

namespace Shopware\Checkout\CartBridge\Product;

use Shopware\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\CartBridge\Product\Struct\CalculatedProduct;
use Shopware\Application\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class ProductPostValidator implements CartProcessorInterface
{
    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        StorefrontContext $context
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

            $valid = $product->getRule()->match(
                $calculatedCart,
                $context,
                $dataCollection
            );

            if ($valid) {
                continue;
            }

            $calculatedCart->getCalculatedLineItems()->remove($product->getIdentifier());
            $cart->getLineItems()->remove($product->getIdentifier());
        }
    }
}
