---
title: Fix promotion custom fields
issue: NEXT-21236
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed `createdComponent` function in `sw-promotion-v2-detail-base` component to load custom fields before returning from the function in case of individual code type.
