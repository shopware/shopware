---
title: Add option for FormAutoSubmit to trigger form validation
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Storefront
* Added opt-out option `useRequestSubmit` to `FormAutoSubmit` storefront plugin to trigger the form submit using `requestSubmit` to trigger client side validation first
* Changed `FormAutoSubmit` to trigger client-side validation first to reduce requests
