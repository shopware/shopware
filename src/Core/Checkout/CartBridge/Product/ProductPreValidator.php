<?php declare(strict_types=1);

namespace Shopware\Checkout\CartBridge\Product;

use Shopware\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Checkout\Cart\LineItem\LineItem;
use Shopware\Context\Struct\StorefrontContext;
use Shopware\Framework\Struct\StructCollection;

class ProductPreValidator implements CartProcessorInterface
{
    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        StorefrontContext $context
    ): void {
        $products = $cart->getLineItems()->filterType(ProductProcessor::TYPE_PRODUCT);
        if ($products->count() <= 0) {
            return;
        }

        /** @var LineItem $product */
        foreach ($products as $product) {
            $payload = $product->getPayload();
            $identifier = $payload['id'];

            if ($dataCollection->has($identifier)) {
                continue;
            }

            $cart->getLineItems()->remove($identifier);
        }
    }
}
