---
title: Change sw-entity-multi-id-select default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-entity-multi-id-select` to emit `update:ids` instead of `change`
___
# Next Major Version Changes
## sw-entity-multi-id-select default event:
* Change event listeners from `@change="onChange"` to `@update:ids="onChange"`
