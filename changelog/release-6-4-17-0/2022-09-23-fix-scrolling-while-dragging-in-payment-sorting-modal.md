---
title: Fix scrolling while dragging in payment sorting modal
issue: NEXT-22515
author: Michel Bade
author_email: m.bade@shopware.com
author_github: cyl3x
---
# Administration
* Added auto scrolling feature to `sw-sortable-list` in `src/Administration/Resources/app/administration/src/app/component/list/sw-sortable-list`
* Fixed auto scrolling of `sw-settings-payment-sorting-modal` with activating the autoscroll feature of `sw-sortable-list` in `src/Administration/Resources/app/administration/src/module/sw-settings-payment/component/sw-settings-payment-sorting-modal/sw-settings-payment-sorting-modal.html.twig`
