<?php

namespace Shopware\CartBridge\Product;

use Shopware\Cart\Cart\Struct\CalculatedCart;
use Shopware\Cart\Cart\Struct\CartContainer;
use Shopware\Cart\Cart\CartProcessorInterface;
use Shopware\Cart\LineItem\LineItem;
use Shopware\Context\Struct\ShopContext;
use Shopware\Framework\Struct\StructCollection;

class ProductPreValidator implements CartProcessorInterface
{
    public function process(
        CartContainer $cartContainer,
        CalculatedCart $calculatedCart,
        StructCollection $dataCollection,
        ShopContext $context
    ): void {

        $products = $cartContainer->getLineItems()->filterType(ProductProcessor::TYPE_PRODUCT);
        if ($products->count() <= 0) {
            return;
        }

        /** @var LineItem $product */
        foreach ($products as $product) {
            if ($dataCollection->has($product->getIdentifier())) {
                continue;
            }

            $cartContainer->getLineItems()->remove($product->getIdentifier());
        }
    }
}