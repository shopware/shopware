---
title: Fix unique ids for address forms
issue: NEXT-39678
---
# Storefront
* Changed the `Storefront/Resources/views/storefront/component/address/address-editor-modal.html.twig` to revert the `edit` and `new` suffixes from `typePrefix`
* Changed the `Storefront/Resources/views/storefront/component/address/address-editor-modal-create-address.html.twig` to add `edit` and `new` to the `idPrefix`
