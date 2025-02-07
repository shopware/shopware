---
title: Fix - delivery address editing during order creation saving leads to Axios error
issue: NEXT-36301
author: Lily
author_email: 78275632+LunaDotGit@users.noreply.github.com
author_github: LunaDotGit
---
# Administration
* Changed `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-address-selection/index.js` Fix address mapping in function addressOptions to seperately set the mapped objects `id` property.
