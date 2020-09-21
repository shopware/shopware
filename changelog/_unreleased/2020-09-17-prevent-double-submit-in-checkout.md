---
title: Prevent double submit in checkout
issue: NEXT-7416
author: Claudio Bianco
author_email: info@claudio-bianco.de
author_github: @claudiobianco
---
# Core
* Changed `CheckoutController` to catch `EmptyCartException` when submitting an order with an empty cart (e.g. due to double-click of the submit button)
___
# Storefront
* Changed checkout order button to use `FormSubmitLoader` to prevent submitting it multiple times
