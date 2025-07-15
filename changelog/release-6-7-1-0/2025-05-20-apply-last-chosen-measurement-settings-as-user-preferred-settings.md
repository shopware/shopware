---
title: Apply last chosen measurement settings as user preferred settings
issue: 8543
---
# Administration
* Changed method `createdComponent` in `sw-product-detail` component to call `initProductMeasurementUnits` method to init product measurement units.
* Changed method `saveProduct` in ``sw-product-detail` component to call `savePreferenceUnits` method to save preferred measurement units.
* Added method `initProductMeasurementUnits` in `sw-product-detail` component to init product measurement units.
* Added method `getPreferredMeasurementUnits` in `sw-product-detail` component to get preferred measurement units.
* Added method `savePreferenceUnits` in `sw-product-detail` component to save preferred measurement units.
* Added computed `lengthUnit` in `sw-product-detail` component to get length unit from product detail store.
* Added computed `weightUnit` in `sw-product-detail` component to get weight unit from product detail store.
