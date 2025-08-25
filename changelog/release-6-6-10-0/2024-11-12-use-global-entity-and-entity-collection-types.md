---
title: Use global entity & entity collection types
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `src/global.types.ts` to directly provide the global `Entity` & `EntityCollection` types without a namespace from the `EntitySchema` namespace of the `@shopware-ag/meteor-admin-sdk` package
* Changed multiple files to remove now unnecessary imports of `Entity` & `EntityCollection` types
