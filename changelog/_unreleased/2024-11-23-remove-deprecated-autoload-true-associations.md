---
title: Remove deprecated autoload === true associations
issue: NEXT-25333
---
# Core
* Changed `Shopware\Core\Checkout\Shipping\ShippingMethodDefinition` to remove deprecated autoload === true for properties:
  * `deliveryTime`
  * `appShippingMethod`
* Changed `Shopware\Core\Checkout\Payment\PaymentMethodDefinition` to remove deprecated autoload === true for `appPaymentMethod`.
* Changed `Shopware\Core\System\NumberRange\NumberRangeDefinition` to remove deprecated autoload === true for `state`.
* Changed `Shopware\Core\Content\Rule\Aggregate\RuleCondition\RuleConditionDefinition` to remove deprecated autoload === true for `appScriptCondition`.
* Changed `Shopware\Core\Content\Product\ProductDefinition` to remove deprecated autoload === true for `tax`.
* Changed `Shopware\Core\Content\Product\Aggregate\ProductMedia\ProductMediaDefinition` to remove deprecated autoload === true for `media`.
