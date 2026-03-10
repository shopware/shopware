---
title: Fix customer registration address validation
issue: 11842
author: Martin Bens
author_email: m.bens@shopware.com
author_github: @SpiGAndromeda
---
# Core
* Added type validation for `billingAddress` field requiring `associative_array` type in `RegisterRoute::validateRegistrationData()`
* Added validation for `shippingAddress` field allowing either `associative_array` or `null` value using `AtLeastOneOf` constraint
* Changed condition logic to prevent validation builder failures when billing address is not a DataBag
___
# API
* Changed `/store-api/account/register` endpoint to return 400 Bad Request instead of 500 Internal Server Error when invalid address data is provided
___
# Upgrade Information
The customer registration endpoint now properly validates billing and shipping address data types. Invalid address data (e.g., string instead of object) will return a 400 status with validation errors instead of 500 Internal Server Error.
