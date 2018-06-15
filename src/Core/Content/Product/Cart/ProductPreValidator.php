<?php declare(strict_types=1);

namespace Shopware\Core\Content\Product\Cart;

use Shopware\Core\Checkout\Cart\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\Cart\Struct\CalculatedCart;
use Shopware\Core\Checkout\Cart\Cart\Struct\Cart;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\CheckoutContext;
use Shopware\Core\Framework\Struct\StructCollection;

class ProductPreValidator implements CartProcessorInterface
{
    public function process(
        Cart $cart,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        CheckoutContext $context
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
