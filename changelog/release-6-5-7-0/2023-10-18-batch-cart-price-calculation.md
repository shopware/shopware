---
title: Batch cart price calculation
issue: NEXT-31153
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Changed `ProductCartProcessor` to call `ProductPriceCalculator::calculate` with all products at once instead of calling it for each product individually  
* Added `EmptyPrice` stub for easier testing cart processor where the price is not relevant
