---
title: Commit cart quantity changes once the edit is finished
issue: 19871
author: Daniel Vien
author_email: d.vienphamtri@shopware.com
---
# Storefront
* Changed `QuantitySelectorPlugin` to apply typed and arrow-key quantity changes on blur or `Enter`, while retaining delayed updates for step buttons and native spinners.
* Added the `submitOnFinish` option and `detail.submitImmediately` on committed quantity changes so `FormAutoSubmitPlugin` and `OffCanvasCartPlugin` can cancel pending updates and use their existing submission handlers immediately.
* Changed `OffCanvasCartPlugin` to listen for quantity changes on the form and serialize cart mutations, including the shipping refresh, using the existing `HttpClient`.
* Added `cancel()` and `flush(...args)` to callbacks returned by `Debouncer.debounce()`.
* Added the `component.product.quantitySelect.cartUpdateHint` snippet to the cart quantity legend.
___
# Upgrade Information
## Cart quantity change events
Custom `change` listeners on a quantity form now receive the finished value when the input loses focus or the user presses `Enter`. On cart quantity controls, these events carry `detail.submitImmediately: true`; custom submission handlers should cancel any pending delayed update and apply the value immediately through their usual submission path.

Step buttons and native spinners still use the configured delay. A move from the input to a step button also retains the delay so the edit and the next step can be sent together.
