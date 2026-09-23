---
title: Improve product box accessibility
issue: #5237
---
# Administration
* Added new cms element config property `boxHeadlineLevel` to `Resources/app/administration/src/module/sw-cms/elements/product-listing/index.ts`.
* Added new `mt-select` to choose a headline level for listings in `Resources/app/administration/src/module/sw-cms/elements/product-listing/config/sw-cms-el-config-product-listing.html.twig`.
___
# Storefront
* Changed `Resources/views/storefront/component/product/card/badges.html.twig` and remove unneeded wrapper divs around `span.badge` product badges.
* Changed `Resources/views/storefront/component/product/card/box-standard.html.twig` to optionally render a headline around `a.product-name` if selected in the CMS element config.
* Changed `Resources/views/storefront/component/product/card/price-unit.html.twig` and add screen reader labels for prices.
* Changed `Resources/views/storefront/component/product/card/action.html.twig` and removed the redundant `title` attributes from the buy button and the detail link.
* Changed `Resources/app/storefront/src/scss/component/_product-box.scss` to style the product badges via the Bootstrap `--bs-badge-*` custom properties.
___
# Upgrade Information
## Headline level for product boxes
Product listings can now render the product name inside a headline element. New listing elements default to `<h2>`;
listing elements that were saved before this change keep the previous markup until the layout is saved again in the
Administration.
