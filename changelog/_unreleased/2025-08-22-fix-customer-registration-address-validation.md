---
title: Fix incorrect validation handling in customer registration endpoint
issue: NEXT-11842
author: Shopware
author_email: m.bens@shopware.com
author_github: @SpiGAndromeda
---
# Core
* Changed validation in `RegisterRoute::validateRegistrationData()` to properly validate billing and shipping address data types
* Added explicit type validation for `billingAddress` field requiring `associative_array` type
* Added validation for `shippingAddress` field allowing either `associative_array` type or `null` value using `AtLeastOneOf` constraint
* Changed condition logic to prevent validation builder failures when billing address is not a DataBag but shipping address is null
___
# API
* Changed Store API `/store-api/account/register` endpoint to return proper 400 status code with JSON validation errors when invalid address data is provided
___
# Upgrade Information

## Customer Registration Validation
The customer registration endpoint now properly validates the data types of billing and shipping addresses.

When invalid address data is sent (e.g., a string instead of an object), the API now returns 400 Bad Request with proper validation errors instead of 500 Internal Server Error.
