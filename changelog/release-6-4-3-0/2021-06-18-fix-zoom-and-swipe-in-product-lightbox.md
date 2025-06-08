---
title: fix zoom and swipe in product lightbox
issue: NEXT-15730
author: Niklas Limberg
author_email: n.limberg@shopware.com
author: NiklasLimberg
author_github: NiklasLimberg
---
# Storefront
*  Changed method `init` in `image-zoom.plugin.js` to create a new vector for `this._storedTransform` instead of assigning a reference
*  Changed method `_updateStoredTransformVector` to create a new vector for `this._storedTransform` and to not use a method that doesn't exist