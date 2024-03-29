---
title: Change sw-entity-many-to-many-select default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-entity-many-to-many-select` to emit `update:entityCollection` instead of `change`
___
# Next Major Version Changes
## sw-entity-many-to-many-select default event:
* Change event listeners from `@change="onChange"` to `@update:entityCollection="onChange"`
