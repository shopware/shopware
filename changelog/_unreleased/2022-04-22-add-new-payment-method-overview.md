---
title: Add new payment method overview
issue: NEXT-20935
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
---
# Administration
* Added new component `sw-settings-payment-overview` for route `sw.settings.payment.overview`
* Added new component `sw-settings-payment-sorting-modal` for sorting payment methods via drag-and-drop
* Added deprecation alert to `sw-settings-payment-list`
* Changed return route for `sw-settings-payment-detail` after saving to new route `sw.settings.payment.overview`
* Added new parameter `icon` to `sw-alert` to show custom icons
* Added new slot `body` to `sw-modal` to replace the complete body
