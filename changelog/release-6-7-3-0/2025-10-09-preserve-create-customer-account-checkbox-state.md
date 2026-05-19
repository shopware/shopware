---
title: Preserve createCustomerAccount checkbox if form is submitted
issue: 12812
author: Dang Nguyen
---
# Storefront
* Changed `storefront\page\checkout\address\register.html.twig` to preserve the `createCustomerAccount` checkbox state when the form is submitted with validation errors, ensuring the checkbox respects user input instead of always defaulting to the `core.loginRegistration.createCustomerAccountDefault` config value.
