---
title: Fix B2C tax-free setting overriding B2B tax logic in cart calculation
issue: 11535
flag:
author: Shopware
author_email:
author_github:
---
# Core
* Fixed tax calculation logic in CartRuleLoader where B2C tax-free settings would incorrectly override B2B customer tax configurations, making B2B tax settings ineffective when B2C tax-free was enabled
___
# API
*  
___
# Administration
*  
___
# Storefront
*  
___
# Upgrade Information
## Tax Calculation Logic
The cart tax detection logic has been improved to properly handle B2B and B2C customer types independently. Previously, when the "Tax-free for B2C" setting was enabled in a country configuration, it would affect B2B customers as well, making the "Tax-free for B2B" setting ineffective. This has been resolved by separating the customer type detection and applying the appropriate tax rules for each customer type.
___
# Next Major Version Changes
