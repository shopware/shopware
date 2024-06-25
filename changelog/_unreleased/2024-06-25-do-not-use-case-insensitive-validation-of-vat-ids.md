---
title: Do not use case insensitive validation of vat ids
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Tax\TaxDetector` and `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator` to not use a case insensitive matching of vat ids
* Changed `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator` to reduce the amount of database calls
