---
title: Fix company tax free detection with disabled vat id check
author: Max
issue: NEXT-16831
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed method `Shopware\Core\Checkout\Cart\Tax\TaxDetector::isCompanyTaxFree()` to fix the company tax free detection with disabled vat id check for invalid vat ids
