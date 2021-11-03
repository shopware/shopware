---
title: Allow wildcard use in alphanumerical values of zip code rules
issue: NEXT-17030
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added abstract `ZipCodeRule` to extend `BillingZipCodeRule` and `ShippingZipCodeRule` from and allow use of a wildcard character to partially match alphanumerical postal codes
___
# Administration
* Changed `sw-condition-billing-zip-code` and `sw-condition-shipping-zip-code` to include a step of choosing whether postal codes are matched aphanumerical or numerical for comprehensibility
