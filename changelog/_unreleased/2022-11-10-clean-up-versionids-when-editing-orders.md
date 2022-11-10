---
title: Clean up versionIds when editing orders
issue: NEXT-23484
flag: FEATURE_NEXT_7530
author: Markus Velt
author_email: m.velt@shopware.com
author_github: raknison
---
# Administration
* Added data prop `hasNewVersionId` to component `sw-order-detail`
* Added method `beforeDestroyComponent` to component `sw-order-detail` to clean up `versionIds` when component gets destroyed
