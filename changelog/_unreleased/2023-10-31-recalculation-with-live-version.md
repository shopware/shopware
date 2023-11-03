---
title: Recalculation with live version
issue: NEXT-31334
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Changed `RecalculationService` to always run the cart processor with the live version inside the context instead of the draft version of the order
