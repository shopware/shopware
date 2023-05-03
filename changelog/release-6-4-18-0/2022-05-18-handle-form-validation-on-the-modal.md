---
title: Handle form validation on the modal
issue: NEXT-21348
---
# Storefront
* Changed method `addressBook` at `Shopware\Storefront\Controller\AddressController` to catch `ConstraintViolationException` and pass the form violations to the view
* Added an option `replaceSelectors` into `formAjaxSubmitOptions` at `/storefront/component/address/address-editor-modal-create-address.html.twig` to determine which component should be replaced after ajax submits the form
* Added new variable `postedData` at `/storefront/component/address/address-editor-modal-create-address.html.twig` to store the previous data, which is submitted at the form
* Added new condition to check `page.address` is not existed before rendering the Creation new Address Form at `/storefront/component/address/address-editor-modal.html.twig` to prevent duplicate when both `address` and `page.address` have the value
