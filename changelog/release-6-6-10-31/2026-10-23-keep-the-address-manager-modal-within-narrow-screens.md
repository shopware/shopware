---
title: Keep the address manager modal within narrow screens
issue: 20382
---
# Storefront
* Changed `@Storefront/storefront/component/address/address-manager-modal.html.twig` to let the shipping/billing toggle buttons wrap when they do not fit next to each other, and made both buttons the same width.
* Changed `@Storefront/storefront/component/address/address-manager-modal-list.html.twig` to let the "Add new address" button label wrap within the modal width.
* Changed `@Storefront/storefront/component/address/address-manager-item.html.twig` and `@Storefront/storefront/page/account/addressbook/address-item.html.twig` to use less horizontal padding on the "Default" badge on small screens.
* Changed `.address-manager-select-address` so that long words in an address break instead of causing horizontal scrolling.
