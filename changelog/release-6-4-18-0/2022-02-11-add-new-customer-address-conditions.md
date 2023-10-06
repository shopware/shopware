---
title: Add new customer address conditions
issue: NEXT-9662
author: Krispin Lütjann
author_email: k.luetjann@shopware.com
author_github: King-of-Babylon
---
# Core
* Added new customer address rule conditions:
    * Checkout/Customer/Rule/BillingCityRule.php
    * Checkout/Customer/Rule/BillingStateRule.php
    * Checkout/Customer/Rule/ShippingCityRule.php
    * Checkout/Customer/Rule/ShippingStateRule.php
___
# Administration
* Added new rule conditions to the `condition-type-data-provider.decorator`:
    * `customerBillingCity`
    * `customerBillingState`
    * `customerShippingCity`
    * `customerShippingState`
