---
title: Change sw-price-field default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-price-field` to emit `update:price` instead of `change`
___
# Next Major Version Changes
## sw-price-field default event:
* Change event listeners from `@change="onChange"` to `@update:price="onChange"`
