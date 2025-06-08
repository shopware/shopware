---
title: Improvement validate with white space
issue: NEXT-17481
---
# Core
* Changed `StringFieldSerializer::encode` in `src/Core/Framework/DataAbstractionLayer/FieldSerializer/StringFieldSerializer.php`.
___
# Storefront
* Added function `_onValidateRequired` in `storefront/src/plugin/forms/form-validation.plugin.js` to validate required field.
* Added data attributes `data-form-validation-required` and `data-form-validation-required-message` to validate and show the error message for the required field in the following files:
  * `storefront/component/address/address-form.html.twig`
  * `storefront/component/address/address-personal.html.twig`
  * `storefront/component/address/address-personal-company.html.twig`
