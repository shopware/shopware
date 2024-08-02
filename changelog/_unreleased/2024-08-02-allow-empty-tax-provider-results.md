---
title: Allow empty tax provider results
issue: NEXT-37525
---
# Core
* Adjusted method `process` in `Shopware\Core\Checkout\Cart\TaxProvider\TaxProviderProcessor` to not throw the exception object if a tax provider result is empty, but added an early exit instead.
