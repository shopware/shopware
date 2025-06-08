---
title: Product Reviews can't be added in Chrome, if a review already exists by another user
issue: NEXT-12181
---
# Storefront
* Added a condition for the `element.removeEventListener` of `src/Storefront/Resources/app/storefront/src/plugin/forms/form-ajax-submit.plugin.js`, to check if `element.removeEventListener` exists, before it is called.
