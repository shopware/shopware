---
title: Fix new version deletion
issue: NEXT-31459
author: Jean-Marc Möckel
author_email: j.moeckel@shopware.com
---
# Administration
* Changed `beforeDestroyComponent` in `sw-order-detail` to use the old `versionContext` in `orderRepository.deleteVersion`
