---
title: Fix content should be replaced which click on other tab in address modal
issue: NEXT-33395
---
# Storefront
* Changed `data-parent` to `data-bs-parent` attribute in `src/Storefront/Resources/views/storefront/component/address/address-editor-modal-create-address.html.twig`
* Changed `data-parent` to `data-bs-parent` attribute in `src/Storefront/Resources/views/storefront/component/address/address-editor-modal-list.html.twig`
* Changed parent selector in `_registerCollapseCallback` method in `Storefront/Resources/app/storefront/src/plugin/address-editor/address-editor.plugin.js`
