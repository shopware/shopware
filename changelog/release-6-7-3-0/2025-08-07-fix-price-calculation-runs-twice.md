---
title: fix price calculation runs twice
issue: #11646
---
# Administration
* Changed the following methods in `sw-price-field` component to end price calculation from running twice:
    * `onPriceGrossInputChange`
    * `onPriceNetInputChange`
    * `onPriceGrossChange`
    * `onPriceNetChange`
    * `convertNetToGross`
    * `convertGrossToNet`
