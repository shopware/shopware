---
title: Change sw-multi-snippet-drag-and-drop default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-multi-snippet-drag-and-drop` to emit `update:value` instead of `change`
___
# Next Major Version Changes
## sw-multi-snippet-drag-and-drop default event:
* Change event listeners from `@change="onChange"` to `@update:value="onChange"`
