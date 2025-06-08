---
title: Fix CMS service collect function for array values
issue: NEXT-23512
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed `getCollectFunction` function in `cmsService` to work with array values by flattening the wrapping array.
