---
title: Fix custom stock storages
issue: NEXT-38104
author: Felix Schneider
author_email: felix.schneider@wimmelbach.de
author_github: @schneider-felix
---
# Core
* Changed multiple services to use `stock` instead of `availableStock`
* Changed subscriber priority of `LoadProductStockSubscriber` to run before `ProductSubscriber` to make sure maxPurchase is correct for `ProductMaxPurchaseCalculator`
___

# Storefront
* Changed twig templates to use `stock` instead of `availableStock`
