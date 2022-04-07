---
title: Fix product API does not output names of variants
issue: NEXT-20875
author: Huy Truong
author_email: huy.truong@shapeandshift.dev
author_github: huytdq94
---
# Core
* Changed `loaded` function in `Shopware\Core\Content\Product\Subscriber\ProductSubscriber` class to set name of variant product if null.
