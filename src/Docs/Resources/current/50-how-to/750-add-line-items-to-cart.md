[titleEn]: <>(Add line items to the cart)
[metaDescriptionEn]: <>(This HowTo will show you how to add line items to the cart)
[hash]: <>(article:how_to_add_line_items_to_the_cart)

## Overview

This guide will show you how to create line items like products, promotion and other types and add them to the cart. The current cart can be fetched using the `\Shopware\Core\Checkout\Cart\SalesChannel\CartService::getCart` method or inside a controller with an argument `Cart`.
This argument will be automatically filled by an argument resolver with the current cart.

## LineItemFactoryRegistry

The registry holds a collection of handlers to create a line item of a specific type. Each line item type needs an own handler.
In default following types are supported:
    * product
    * promotion
    * credit
    * custom
If the type is not supported, it will throw a `\Shopware\Core\Checkout\Cart\Exception\LineItemTypeNotSupportedException` exception.
    
```php
class SomeController
{
    /**
     * @var \Shopware\Core\Checkout\Cart\LineItemFactoryRegistry
     */
    private $factory;
    
    /**
     * @var \Shopware\Core\Checkout\Cart\SalesChannel\CartService
     */
    private $cartService;

    public function add(\Shopware\Core\Checkout\Cart\Cart $cart, \Shopware\Core\System\SalesChannel\SalesChannelContext $context): void
    {
        // Create product line item
        $lineItem = $this->factory->create([
            'type' => 'product',
            'referencedId' => '<product-id>',
            'quantity' => 5,
            'payload' => ['key' => 'value']
        ], $context);
        
        $this->cartService->add($cart, $lineItem, $context);

        // Create promotion line item
        $lineItem = $this->factory->create(['type' => 'promotion', 'referencedId' => '<code>'], $context);
        $this->cartService->add($cart, $lineItem, $context);
    }
}
```

## Create new factory handler

You need to create a new class which implements the interface `\Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface` and needs to be registered in the di container with the tag `shopware.cart.line_item.factory`.

Example coass:

```php
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\LineItemFactoryHandler\LineItemFactoryInterface;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class MyHandler implements LineItemFactoryInterface {

    public function supports(string $type): bool
    {
        return $type === 'MyType';
    }

    public function create(array $data, SalesChannelContext $context): LineItem
    {
        return new LineItem($data['id'], 'MyType', $data['referencedId'] ?? null, 1);
    }

    public function update(LineItem $lineItem, array $data, SalesChannelContext $context): void
    {
        if (isset($data['referencedId'])) {
            $lineItem->setReferencedId($data['referencedId']);
        }
    }
}
```



