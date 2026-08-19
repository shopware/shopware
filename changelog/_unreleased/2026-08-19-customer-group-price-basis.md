---
title: Add price basis setting to customer groups
author: Max Stegmeyer
author_github: @mstegmeyer
---
# Core
* Added field `price_basis` to `customer_group` entity, allowing `net` to make the stored net price authoritative independently of the gross/net display mode. With the `v6.8.0.0` feature flag active, `gross` is accepted as well and keeps the display driven selection
* Added `Shopware\Core\Checkout\Cart\Price\AbstractPriceSelector` and `Shopware\Core\Checkout\Cart\Price\PriceSelector`, the decoratable service selecting the authoritative stored price value
* Added `Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice`
* Changed `ProductPriceCalculator`, `DeliveryCalculator`, `CurrencyPriceCalculator` and the script price facades to select stored price values through `AbstractPriceSelector`
* Changed HTTP cache cookie and sales channel context hash to include a tax rule fingerprint when the current customer group uses the `net` price basis
___
# Administration
* Added a "Price display & calculation" card to the customer group detail page with a combined mode selection (gross prices, net prices, gross prices with net price base) writing `displayGross` and `priceBasis` together. With the `v6.8.0.0` feature flag active, the selection writes explicit `priceBasis` values instead of `null`
* Added component `sw-settings-customer-group-price-preview`, visualizing customer price, tax and merchant proceeds for two example tax rates per selected mode
* Deprecated block `sw_settings_customer_group_detail_content_card_display_gross` in `sw-settings-customer-group-detail.html.twig`, use `sw_settings_customer_group_detail_content_price_display_card_mode` instead
