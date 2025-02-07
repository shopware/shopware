---
title: Improve loading performance admin shipping detail page
issue: NEXT-37478
author: Fabian Boensch
author_email: f.boensch@shopware.com
author_github: @En0Ma1259
---
# Administration
* Changed `sw-settings-shipping-price-matrix` prices output. Only first price will be rendered. Remaining prices can be displayed using a button
* Added seperated call in `sw-settings-shipping-detail` to load restricted rule ids. Restricted rules don't need to be validated locally anymore
___
# Next Major Version Changes
## Administration removed associations
* Removed `calculationRule` association in `shippingMethodCriteria()` in `sw-settings-shipping-detail`.
* Removed `conditions` association in `ruleFilterCriteria()` and `shippingRuleFilterCriteria()` in `sw-settings-shipping-price-matrix`
