---
title: Do not use case insensitive validation of vat ids
issue: NEXT-38721
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed `Shopware\Core\Checkout\Cart\Tax\TaxDetector` and `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator` to not use a case insensitive matching of vat ids
* Changed `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerVatIdentificationValidator` to reduce the amount of database calls
___
# Next Major Version Changes
## Vat Ids will be validated case sensitive
Vat Ids will now be checked for case sensitivity, which means that most Vat Ids will now have to be upper case, depending on their validation pattern.
For customers without a company, this check will only be done on entry, so it is still possible to checkout with an existing lower case Vat Id.
For customers with a company, this check will be done at checkout, so they will need to change their Vat Id to upper case.
