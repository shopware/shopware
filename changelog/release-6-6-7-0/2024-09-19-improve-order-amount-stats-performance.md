---
title: Improve order amount stats performance
issue: NEXT-38418
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `DashboardController` to pass the context to the `orderAmountService`
* Changed `OrderAmountService` to use `$rounding` from context, fix null timezone & prefetch the paid transaction id
