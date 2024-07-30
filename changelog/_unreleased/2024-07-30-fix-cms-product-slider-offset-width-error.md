---
title: Fix cms product slider offsetWidth error
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed method `setSliderRowLimit` in `sw-cms-el-product-slider` component to check undefined `productHolder` ref when reading `offsetWidth`.
