---
title: Improve error suggestions
issue: NEXT-26715
---
# Storefront
* Changed method `\Shopware\Core\Checkout\Customer\Validation\AddressValidationFactory::buildCommonValidation` by adding a custom snippet key for storefront validation hints.
* Changed method `\Shopware\Core\Checkout\Customer\Validation\CustomerValidationFactory::addConstraints` by adding a custom snippet key for storefront validation hints.
* Changed `Storefront/Resources/views/storefront/utilities/form-violation.html.twig` template to show advanced validation suggestions if set.
* Added snippets:
  * `VIOLATION::INVALID_EMAIL_FORMAT_ERROR`
  * `VIOLATION::NOT_EQUAL_ERROR`
  * `VIOLATION::FIRST_NAME_IS_BLANK_ERROR`
  * `VIOLATION::LAST_NAME_IS_BLANK_ERROR`
  * `VIOLATION::STREET_IS_BLANK_ERROR`
  * `VIOLATION::CITY_IS_BLANK_ERROR`
  * `VIOLATION::COUNTRY_IS_BLANK_ERROR`
  * `VIOLATION::ADDITIONAL_ADDR1_IS_BLANK_ERROR`
  * `VIOLATION::ADDITIONAL_ADDR2_IS_BLANK_ERROR`
  * `VIOLATION::PHONE_NUMBER_IS_BLANK_ERROR`
  * `VIOLATION::PHONE_NUMBER_IS_TOO_LONG`
  * `VIOLATION::FIRST_NAME_IS_TOO_LONG`
  * `VIOLATION::LAST_NAME_IS_TOO_LONG`
  * `VIOLATION::TITLE_IS_TOO_LONG`
  * `VIOLATION::ZIPCODE_IS_TOO_LONG`
