---
title: Shipping cost discount is not recalculated correctly
issue: NEXT-34189
---
# Core
* Changed method `process` of class `Shopware\Core\Checkout\Cart\Delivery\DeliveryProcessor` to filter our delivery discounts that are not applicable anymore if the shipping costs are changed.
* Changed method `transform` of class `Shopware\Core\Checkout\Cart\Order\Transformer\DeliveryTransformer` to add `shippingOrderAddressId` and `shippingOrderAddressVersionId` to the data if the existing delivery set them in the extension.
* Changed method `convertToOrder` of class `Shopware\Core\Checkout\Cart\Order\OrderConverter` to prevent throw exception if the delivery has `originalAddressId` in the extension.
* Added `self::ORIGINAL_ADDRESS_ID` and `self::ORIGINAL_ADDRESS_VERSION_ID` into class `Shopware\Core\Checkout\Cart\Order\OrderConverter` to store the original address id and version id of existing delivery.
* Changed method `toggleAutomaticPromotion` of class `Shopware\Core\Checkout\Cart\Order\RecalculationService` to include deliveries when converting the cart to order if recalculate cart does not skip automatic promotions.
* Added new method `productNotFound` into class `Shopware\Core\Checkout\Cart\CartException` to handle the exception when the product is not found.
* Added new method `deliveryWithoutAddress` into class `Shopware\Core\Checkout\Order\OrderException` to handle the exception when the delivery does not have an address.
* Added new method `invalidPriceDefinition` into class `Shopware\Core\Checkout\Promotion\PromotionException` to handle the exception when the price definition is invalid.
* Deprecated class `Shopware\Core\Checkout\Promotion\Exception\InvalidPriceDefinitionException` and replaced it with `Shopware\Core\Checkout\Promotion\PromotionException::invalidPriceDefinition`.
* Deprecated class `Shopware\Core\Checkout\Order\Exception\DeliveryWithoutAddressException` and replaced it with `Shopware\Core\Checkout\Order\OrderException::deliveryWithoutAddress`.
___
# Administration
* Added a new data `deliveryDiscountsToDelete` into component `sw-order-detail` to handle the deletion of delivery discounts.
* Added a new computed data `deliveryDiscounts` into component `sw-order-detail` to handle the delivery discounts.
* Changed method `onSaveEdits` in component `sw-order-detail` to handle the deletion of delivery discounts.
* Changed method `onSaveAndRecalculate` in component `sw-order-detail` to handle the deletion of delivery discounts.
* Changed method `onRecalculateAndReload` in component `sw-order-detail` to handle the deletion of delivery discounts.
