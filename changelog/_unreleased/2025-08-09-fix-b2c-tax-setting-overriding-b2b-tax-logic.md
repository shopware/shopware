---
title: Fix B2C tax-free setting overriding B2B tax logic in cart calculation
issue: 11535
---
# Core
* Changed tax calculation in CartRuleLoader so B2C tax-free setting no longer overrides B2B tax settings
___
# Upgrade Information
## Tax Calculation Logic
The tax-free detection logic if the cart changed to handle B2B and B2C customers separately.
Previously, enabling "Tax-free for B2C" in the country settings also affected B2B customers.
Now, tax rules are applied **correctly** based on customer type.
