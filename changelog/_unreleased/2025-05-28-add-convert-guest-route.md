---
title: Add guest conversion route to Store API
author: Max Stegmeyer
author_email: m.stegmeyer@shopware.com
author_github: @mstegmeyer
---
# Core
* Added a new route to the Store API to convert a guest user to a registered user by passing a password to `\Shopware\Core\Checkout\Customer\SalesChannel\ConvertGuestRoute`. 
* Added new `Shopware\Core\Checkout\Customer\Validation\PasswordValidationFactory` to unify password validation for different validation scenarios, e.g. registration, password reset, guest conversion.
