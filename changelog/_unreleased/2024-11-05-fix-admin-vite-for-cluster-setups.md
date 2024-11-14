---
title: Fix Admin Vite for cluster setups
issue: NEXT-35977
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added `ViteFileAccessorDecorator` to fix the vite base path for cluster setups
* Added `vite-plugins/asset-path-plugin` to fix module preloading with cluster setups
