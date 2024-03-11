---
title: Change sw-extension-rating-stars default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-extension-rating-stars` to emit `update:rating` instead of `rating-changed`
___
# Next Major Version Changes
## sw-extension-rating-stars default event:
* Change event listeners from `@rating-changed="onChange"` to `@update:rating="onChange"`
