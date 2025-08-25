---
title: Reset customer on cancel order create modal
issue: https://github.com/shopware/shopware/issues/7454
author_github: @En0Ma1259
---
# Administration
* Changed `cancelCart` method in `src/Administration/Resources/app/administration/src/module/sw-order/component/sw-order-create-initial-modal/index.ts`. Will remove customer and not emit "modal-close" anymore
