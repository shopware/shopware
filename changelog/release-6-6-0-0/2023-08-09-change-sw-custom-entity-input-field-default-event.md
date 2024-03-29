---
title: Change sw-custom-entity-input-field default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-custom-entity-input-field` to emit `update:value` instead of `change`
___
# Next Major Version Changes
## sw-custom-entity-input-field default event:
* Change event listeners from `@change="onChange"` to `@update:value="onChange"`
