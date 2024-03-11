---
title: Change sw-media-library default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-media-library` to emit `update:selection` instead of `media-selection-change`
___
# Next Major Version Changes
## sw-media-library default event:
* Change event listeners from `@media-selection-change="onChange"` to `@update:selection="onChange"`
