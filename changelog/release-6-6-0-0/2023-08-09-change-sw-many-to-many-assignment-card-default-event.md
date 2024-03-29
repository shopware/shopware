---
title: Change sw-many-to-many-assignment-card default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-many-to-many-assignment-card` to emit `update:entityCollection` instead of `change`
___
# Next Major Version Changes
## sw-many-to-many-assignment-card default event:
* Change event listeners from `@change="onChange"` to `@update:entityCollection="onChange"`
