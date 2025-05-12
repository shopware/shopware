---
title: Fixed the listing when using the browser back button and filters
issue: NEXT-37455
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `ListingPlugin` to use native `window.history` instead of `HistoryUtil`
  * This fixes the issue that the listing was not updated when using the browser's back button
