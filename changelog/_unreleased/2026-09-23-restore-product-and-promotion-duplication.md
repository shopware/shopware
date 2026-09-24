---
title: Restore product and promotion duplication
issue: 20528
author: Jonas Elfering
author_email: j.elfering@shopware.com
author_github: @keulinho
---
# Core
* Changed cloning so write-protected derived fields (product `childCount`, promotion `orderCount` and `ordersPerCustomerCount`) are no longer rejected as invalid overwrites; product duplication keeps its correct variant count, duplicated promotions start with zero total and per-customer redemptions instead of inheriting the source promotion's usage.