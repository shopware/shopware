---
title: New adresse manager modal
issue: NEXT-19776
---
# Core
* Added `ADDRESS_SELECTION_REWORK` feature flag to enable/disable the new address manager modal and account address page redesign 
___
# API
* Added `frontend.account.addressmanager.switch` route to switch active addresses in address manager modal
* Added `frontend.account.addressmanager.get` route to get the address manager modal content
* Added `frontend.account.addressmanager` route to handle address creation and editing
___
# Storefront
* Deprecated `src/Storefront/Resources/views/storefront/component/address/address-editor-modal.html.twig`, use `src/Storefront/Resources/views/storefront/component/address/address-manager-modal.html.twig` instead
* Deprecated `src/Storefront/Resources/views/storefront/component/address/address-editor-modal-list.html.twig`, use `src/Storefront/Resources/views/storefront/component/address/address-manager-modal-list.html.twig` instead
* Deprecated `src/Storefront/Resources/views/storefront/component/address/address-editor-modal-create-address.html.twig`, use `src/Storefront/Resources/views/storefront/component/address/address-manager-modal-create-address.html.twig` instead
* Changed `src/Storefront/Controller/AddressController.php` and added new routes `AddressController::addressManager`, `AddressController::addressManagerGet` and `AddressController::addressManagerSwitch` for the new address manager modal
* Changed `src/Storefront/Resources/views/storefront/page/account/addressbook/index.html.twig` and deprecated old page content and added new page content
* Changed `src/Storefront/Resources/views/storefront/page/checkout/confirm/confirm-address.html.twig` to use new address manager modal
* Added new address manager modal plugin `src/Storefront/Resources/app/storefront/src/plugin/address-manager/address-manager.plugin.js`
* Added new search plugin `src/Storefront/Resources/app/storefront/src/plugin/address-search/address-search.plugin.js`
* Added `src/Storefront/Resources/views/storefront/component/address/addresses-base.html.twig` as a component for the address manager modal and account address page
___
# Upgrade Information

## Deprecated old address editor

The `address-editor.plugin.js` is deprecated and will be removed in 6.7.0, extend `address-manager.plugin.js` instead.
The `address-editor-modal.html.twig` is deprecated and will be removed in 6.7.0, extend `address-manager-modal.html.twig` instead.
The `address-editor-modal-list.html.twig` is deprecated and will be removed in 6.7.0, extend `address-manager-modal-list.html.twig` instead.
The `address-editor-modal-create-address.html.twig` is deprecated and will be removed in 6.7.0, extend `address-manager-modal-create-address.html.twig` instead.

## Added new address search plugin

Added `address-search.plugin.js` to search customer addresses in the new modal and address account page.
___
# Next Major Version Changes

The `address-editor-modal.html.twig`, `address-editor-modal-list.html.twig`, `address-editor-modal-create-address.html.twig` and `address-editor.plugin.js` has been removed.
The `src/Storefront/Resources/views/storefront/page/account/addressbook/index.html.twig` page content is updated