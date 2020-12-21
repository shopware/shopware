---
title: Register Customer separately in each sales channel
issue: NEXT-10973
---
# Core
*  Added `SalesChannelContext` getter into `CustomerEmailUnique` constraint.
*  Changed `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUniqueValidator::validate()` method that allows validating unique email in every sales channel.
___
# Upgrade Information
*  Customer email is not unique from all customers anymore, instead it will unique from other customers' email in a same sales channel.
*  The `$context` property in `Shopware\Core\Checkout\Customer\Validation\Constraint\CustomerEmailUnique` is deprecated, using `SalesChannelContext $salesChannelContext` to get the context instead.
