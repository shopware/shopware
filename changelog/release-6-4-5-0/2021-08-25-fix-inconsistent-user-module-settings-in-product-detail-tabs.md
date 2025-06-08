---
title: Fix inconsistent user module settings in product detail tabs
issue: NEXT-16859
---
# Administration
*  Changed behaviour of `getAdvancedModeSetting()` in `sw-product-detail/index.js` to consider added or removed cards, even when the user already made visibility settings
