---
title: Rules consider nested line items
issue: NEXT-13765
author: Michael Telgmann
author_github: @mitelg
---
# Core
* Changed line item rules, so they consider also nested line items
___
# Upgrade Information
## LineItems rules behaviour changed
The rules for line items are now considering also nested line items.
Before the change, only the first level of line items was taken into account.
Check your rules, if they still take effect as intended.
