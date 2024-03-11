---
title: Change sw-import-export-entity-path-select default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-import-export-entity-path-select` to emit `update:value` instead of `change`
___
# Next Major Version Changes
## sw-import-export-entity-path-select default event:
* Change event listeners from `@change="onChange"` to `@update:value="onChange"`
