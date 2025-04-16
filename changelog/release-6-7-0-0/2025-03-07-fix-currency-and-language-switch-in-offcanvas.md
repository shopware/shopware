---
title: Fix currency and language switch in OffCanvas
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `offcanvas-menu.plugin.js` 
  * To make sure JavaScript events are set correctly (so `form-add-dynamic-redirect-plugin.js` is working as expected).
  * To allow to open the OffCanvas menu via URL parameter (`?offcanvas=menu`).
