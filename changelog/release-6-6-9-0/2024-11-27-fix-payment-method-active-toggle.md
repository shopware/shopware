---
title: Fix payment method active toggle
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed `setPaymentMethodActive` method in `sw-payment-card` component to receive the switch field active state and prevent emitting `set-payment-active` event if the active state is unchanged.
