---
title: Change sw-extension-select-rating default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-extension-select-rating` to emit `update:value` instead of `change`
___
# Next Major Version Changes
## sw-extension-select-rating default event:
* Change event listeners from `@change="onChange"` to `@update:value="onChange"`
