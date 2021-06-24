---
title: Add new rule to query strike price in dynamic product group
issue: NEXT-15177
---
# Core
*  Added array `percentage` to `Shopware\Core\Framework\DataAbstractionLayer\Pricing\Price` class.
*  Changed `encode` and `decode` function in `Shopware\Core\Framework\DataAbstractionLayer\FieldSerializer\PriceFieldSerializer` class to save `percentage` value to price json. 
*  Changed `buildAccessor` function in `Shopware\Core\Framework\DataAbstractionLayer\Dbal\FieldAccessorBuilder\PriceFieldAccessorBuilder` class to query `percentage` of `price` in Product.
___
# Administration
*  Added const `allowedJsonAccessors` in `product-stream-condition.service.js` to define allowed json accessors can use in product stream.
*  Changed computed property `fields` in `src/module/sw-product-stream/component/sw-product-stream-filter/index.js` to have correct getter with json accessor.
*  Changed computed `fieldDefinition` in `src/module/sw-product-stream/component/sw-product-stream-value/index.js` to get correct field definition with json accessor.
*  Changed computed `options` in `src/module/sw-product-stream/component/sw-product-stream-field-select/index.js` to get correct options with json accessor.
