---
title: Fix change variant price on detail page
issue: NEXT-14348
---
# Administration
* Added block `sw_product_price_form_fields` in `src/module/sw-product/component/sw-product-price-form/sw-product-price-form.html.twig` to wrap block `sw_product_price_form_tax_field` and `sw_product_price_form_price_field`
* Added block `sw_product_price_form_link` in `src/module/sw-product/component/sw-product-price-form/sw-product-price-form.html.twig` to wrap block `sw_product_price_form_advanced_prices_link` and `sw_product_price_form_maintain_currencies_link`
* Changed method `removePriceInheritation` in `src/module/sw-product/component/sw-product-price-form/index.js` to set default price values from parent product
* Changed computed property `prices` in `src/module/sw-product/component/sw-product-price-form/index.js` to have correct getter and setter
* Changed computed property `parentPrices` in `src/module/sw-product/component/sw-product-price-form/index.js` to make it more readable
* Changed method `inheritationCheckFunction` in `src/module/sw-product/component/sw-product-price-form/index.js` to validate price inheritance status
* Changed block `sw_product_price_form_price_field` in `src/module/sw-product/component/sw-product-price-form/sw-product-price-form.html.twig` by adding label attribute to show inheritance switch
* Changed style of class `.sw-help-text` in `src/app/component/base/sw-help-text/sw-help-text.scss` to allow user hover the help text even it is disabled
* Changed `viewer.privileges` in `src/module/sw-product/acl/index.js` to add `number_range:read` and `number_range_type:read` permission
