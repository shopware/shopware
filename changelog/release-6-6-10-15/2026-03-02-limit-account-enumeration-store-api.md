---
title: Limit account enumeration via Store API
---
# Core
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\AccountService` to always throw a `BadCredentialsException` if the user is not found to not allow user account enumeration.
___
# Upgrade Information

## Critical Fixes

### LoginRoute and AccountService don't throw CustomerNotFoundException
The `LoginRoute` and `AccountService` have been updated to no longer throw a `CustomerNotFoundException` when a login attempt is made with an email address that does not exist in the system.
Instead, they will now throw a generic `BadCredentialsException` without revealing whether the email address is registered or not.
This change enhances security by preventing potential attackers from enumerating valid email addresses through error messages.
