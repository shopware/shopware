---
title: Set aria-current page for activateNavigationId in navbar
issue: NEXT-40842
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `navbar.plugin.js` to set `aria-current="page"` for the active navigation item depending on `window.activeNavigationId`.

This change improves accessibility by indicating the current page in the main navigation menu.
