---
title: Remove not needed cache hash creation for cache in the CurrencyFormatter and add cache reset
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Removed not needed cache hash creation in the `Shopware\Core\System\Currency\CurrencyFormatter` for performance
* Added `reset` method to the `Shopware\Core\System\Currency\CurrencyFormatter` to reset the internal `NumberFormatter` cache on `kernel.reset`
