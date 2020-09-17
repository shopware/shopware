---
title: Prevent double submit in checkout
issue: NEXT-7416
author: Claudio Bianco
author_email: info@claudio-bianco.de
author_github: @claudiobianco
---
# Core
* Catch `EmptyCartException` when submitting order with empty cart (e.g. due to double click submit button)
___
# Storefront
* Changed checkout order button to use `FormSubmitLoader` to prevent submitting it multiple times
