---
title: Fixing nested line items display
issue: NEXT-14284
---
# Storefront
*  Removed snippet `account.orderItemInfoFree`
*  Changed behavior in account order overview:
   * Prices always show a price of 0,00 instead of a "free" snippet
   * When displaying nested line items, the unit prices will be correctly displayed instead of a wrong "free" statement
