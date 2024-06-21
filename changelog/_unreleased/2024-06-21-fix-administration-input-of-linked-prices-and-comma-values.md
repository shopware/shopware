---
title: Fix administration input of linked prices and comma values
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Administration
* Removed unused method `keymonitor` from the `sw-price-field` and `sw-product-price-form`
* Changed `sw-price-field` to not update the value on every input, as this is already done by the number field component
* Changed `sw-number-field` (in particular `sw-number-field-deprecated`) to dispatch the `input-change` event with the actual value and not just the parsed float value
