---
title: Processing of nested line items
date: 2020-03-24
area: checkout
tags: [checkout, cart, line-items]
---

## Context

We want to handle nested order line items.
Currently, the line items are available nested, but all cart processors only consider the first level of line items.
On one hand, we could implement all cart processors, that they process all levels of line items, but on the other hand,
all nested line items are added via plugins, which would implement their own processing logic.

## Decision

The core cart processors will continue to work with `getFlat()` in enrich. This way the required data for all items in the
cart will be fenced and each item could also be processed by its processor.
The `process` method on other hand will still not work with `getFlat()`, but will only take care of line items that are on the first level. 
This way there will be no collisions in the processing of these line items. A plugin that reuses core line items can
easily call the other processors to handle the nested line items themselves.

Example:

```
<?php declare(strict_types=1);

namespace Shopware\Core\Checkout\Cart;

use Shopware\Core\Checkout\Cart\Error\IncompleteLineItemError;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Cart\ProductCartProcessor;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class PluginCartProcessor implements CartProcessorInterface
{
    /**
     * @var CreditCartProcessor
     */
    private $creditCartProcessor;

    /**
     * @var ProductCartProcessor
     */
    private $productCartProcessor;

    public function __construct(CreditCartProcessor $creditCartProcessor, ProductCartProcessor $productCartProcessor)
    {
        $this->creditCartProcessor = $creditCartProcessor;
        $this->productCartProcessor = $productCartProcessor;
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        $lineItems = $original->getLineItems()->filterType('plugin-line-item-type');

        /*
         * Structure of the plugin line item:
         * - plugin line item
         *      - product line item(s)
         *      - credit line item(s)
         */
        foreach ($lineItems as $lineItem) {
            $this->calculate($lineItem, $original, $context, $behavior, $data);
            $toCalculate->add($lineItem);
        }
    }

    private function calculate(LineItem $lineItem, Cart $original, SalesChannelContext $context, CartBehavior $behavior, CartDataCollection $data): void
    {
        if (!$lineItem->hasChildren()) {
            $original->remove($lineItem->getId());
            $original->addErrors(new IncompleteLineItemError($lineItem->getId(), 'children'));

            return;
        }

        $tempOriginalCart = new Cart('temp-original', $original->getToken());
        $tempCalculateCart = new Cart('temp-calculate', $original->getToken());

        // only provide the nested products and credit items
        $tempOriginalCart->setLineItems(
            $lineItem->getChildren()
        );

        // first start product calculation - all required data for the product processor is already loaded and stored in the CartDataCollection
        $this->productCartProcessor->process($data, $tempOriginalCart, $tempCalculateCart, $context, $behavior);

        // now calculate the credit, the credit is scoped to the already calculated products - all required data for the credit processor is already loaded and stored in the CartDataCollection
        $this->creditCartProcessor->process($data, $tempOriginalCart, $tempCalculateCart, $context, $behavior);

        // after all line items calculated - use them as new children
        $lineItem->setChildren(
            $tempCalculateCart->getLineItems()
        );
    }
}
```


## Consequences

The plugins have to implement their only processing logic or alternatively extend shopware's cart processors, when using
a specific implementation of nested line items.
