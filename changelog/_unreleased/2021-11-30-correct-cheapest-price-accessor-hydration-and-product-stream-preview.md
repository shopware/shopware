---
title: Correct cheapest price accessor hydration and product stream preview
issue: NEXT-18604
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Administration
* Added `AdminProductStreamController` for product stream preview using the sales channel product repository
* Added `productStreamPreviewService` for fetching product stream preview
* Deprecated data properties `systemCurrency` and `criteria`, computed properties `productRepository` and `currencyRepository`, method `loadSystemCurrency` in component `sw-product-stream-modal-preview`
* Changed `sw-product-stream-modal-preview` to retrieve product preview from `productStreamPreviewService`
