---
title: Clean cart before serialization
issue: NEXT-21642
flag: v6.5.0.0
author: Timo Helmke, Sebastian König
author_email: t.helmke@kellerkinder.de
author_github: t2oh4e
---
# Core
* Custom fields in cart will now be removed when not used in cart rules
___
# Upgrade Information
## Custom fields in cart
Custom fields will now be removed from the cart for performance reasons. Add the to the allow list with CartBeforeSerializationEvent if you need them in cart.
___
# Next Major Version Changes
## Custom fields in cart removed:
* Add custom fields to custom field allow list in CartBeforeSerializationEvent if you need them in cart. Custom fields used in cart rules will not be removed by default.
