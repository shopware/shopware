---
title: Added a customer before delete flow trigger
issue: NEXT-37412
author: Simon Fiebranz
author_email: s.fiebranz@shopware.com
author_github: CR0YD
---
# Core
* Added a `customer` property, which is the array representation of a customer entity, to the accessible data of `CustomerDeleteEvent`.
* Deprecated the properties `customerId`, `customerNumber`, `customerEmail`, `customerFirstName`, `customerLastName`, `customerCompany` and `customerSalutationId` that were contained in the accessible data of `CustomerDeleteEvent`. These values, if accessed in mail templates that are send via the `Checkout > Customer > Delete` flow trigger, should from now on be replaced by using the respective value of the customer object, e.g. `customerFirstName` -> `customer.firstName`. 
___
# Next Major Version Changes
## Removal of deprecated properties of `CustomerDeletedEvent`
* The deprecated properties `customerId`, `customerNumber`, `customerEmail`, `customerFirstName`, `customerLastName`, `customerCompany` and `customerSalutationId` of `CustomerDeleteEvent` will be removed and cannot be accessed anymore in a mail template when sending a mail via the `Checkout > Customer > Deleted` flow trigger.
