---
title: Fix link to data protection regulations does not work
issue: NEXT-7553
---
# Storefront
* Changed `storefront/src/ultitlity/modal-extension/AjaxModalExtensionUtil::_openModal` method to call `_registerAjaxModalExtension` again on the newly generated modal.
