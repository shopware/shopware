---
title: Change Scroll-Up-Button to be called just on click
issue: NEXT-18166
author: Sebastian König
author_email: s.koenig@tinect.de
author_github: @tinect
---
# Storefront
* Removed registration of `touchstart` in `scroll-up.plugin.js` to prevent unintentional scrolling
