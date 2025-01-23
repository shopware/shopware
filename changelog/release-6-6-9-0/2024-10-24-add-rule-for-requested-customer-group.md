---
title: Add rule for requested customer group
issue: NEXT-39301
author: Robin Homberg
author_email: robin.homberg@flagbit.de
author_github: @robin-homberg
---
# Administration
* Changed `src/Administration/Resources/app/administration/src/app/decorator/condition-type-data-provider.decorator.ts` to make CustomerRequestedGroupRule available in Administration
* Changed administration snippets to add translations for CustomerRequestedGroupRule
___
# Core
* Added `src/Core/Checkout/Customer/Rule/CustomerRequestedGroupRule.php` to create new Rule conditions using customers requestedCustomerGroup
* Changed `src/Core/Checkout/DependencyInjection/rule.xml` to register CustomerRequestedGroupRule
