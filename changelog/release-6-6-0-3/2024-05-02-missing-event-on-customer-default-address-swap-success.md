---
title:              Dispatch events after default is successfully set      # Required
issue:                                            # Required

author:             Tommy Quissens                          # Optional for shopware employees, Required for external developers
author_email:       tommy.quissens@meteor.be                   # Optional for shopware employees, Required for external developers
author_github:      @quisse                                 # Optional
---
# Core
Events are added when a customer successfully swaps their default address. `\Shopware\Core\Checkout\Customer\Event\CustomerDefaultShippingAddressSetEvent` and `\Shopware\Core\Checkout\Customer\Event\CustomerDefaultBillingAddressSetEvent` are added.
