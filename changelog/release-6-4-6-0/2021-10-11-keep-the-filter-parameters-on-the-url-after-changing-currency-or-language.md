---
title: Keep the filter parameters on the url after changing currency or language
issue: NEXT-13113
---
# Storefront
* Added a new function `_updateRedirectParameters` to `src/Storefront/Resources/app/storefront/src/plugin/forms/form-auto-submit.plugin.js` to update input `redirectParameters` when changing form.
* Added a new function `_createInputForRedirectParameter` to `src/Storefront/Resources/app/storefront/src/plugin/forms/form-auto-submit.plugin.js` to generate html content of input `redirectParameters`.
