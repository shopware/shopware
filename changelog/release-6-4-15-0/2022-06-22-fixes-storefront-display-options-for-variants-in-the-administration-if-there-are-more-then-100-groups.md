---
title: Fix displaying of variant groups, if there are more then 100 groups
issue: NEXT-20792
author: Dominik Mank
author_email: d.mank@web-fabric.de
author_github: dominikmank
---
# Administration
* Removed the limit from the groupRepository in `sw-product-detail-variants/index.js`, so all variant groups will be loaded in the variant generator
