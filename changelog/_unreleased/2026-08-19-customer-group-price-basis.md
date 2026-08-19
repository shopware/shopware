---
title: Add price basis setting to customer groups
author: Max Stegmeyer
author_github: @mstegmeyer
---
# Core
* Added field `price_basis` to `customer_group` entity, allowing `net` to make the stored net price authoritative independently of the gross/net display mode
* Added `Shopware\Core\Checkout\Cart\Price\AbstractPriceSelector` and `Shopware\Core\Checkout\Cart\Price\PriceSelector`, the decoratable service selecting the authoritative stored price value
* Added `Shopware\Core\Checkout\Cart\Price\Struct\SelectedPrice`
* Changed `ProductPriceCalculator`, `DeliveryCalculator`, `CurrencyPriceCalculator` and the script price facades to select stored price values through `AbstractPriceSelector`
* Changed HTTP cache cookie and sales channel context hash to include a tax rule fingerprint when the current customer group defines a price basis
___
# Administration
* Added a price basis single-select to the customer group detail page
