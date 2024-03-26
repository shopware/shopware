---
title: Change sw-media-breadcrumbs default event
issue: NEXT-28991
author: Sebastian Seggewiß
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `sw-media-breadcrumbs` to emit `update:currentFolderId` instead of `media-folder-change`
___
# Next Major Version Changes
## sw-media-breadcrumbs default event:
* Change event listeners from `@media-folder-change="onChange"` to `@update:currentFolderId="onChange"`
