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
* Changed error handling to complete the fix for issue #11842 which was partially addressed in PR #11712
___
# Upgrade Information

## Customer Registration Validation
The customer registration endpoint now properly validates the data types of billing and shipping addresses. This completes the fix for issue #11842, building upon PR #11712 which addressed the initial error handling.

### Evolution of the fix:
1. **Original issue**: Invalid address data (e.g., a string instead of an object) returned 200 status with empty HTML content, causing CORS errors
2. **PR #11712**: Fixed the immediate crash by improving error code handling, but still returned 500 status
3. **This fix**: Completes the solution by adding proper validation constraints that return 400 status with detailed validation errors

### Before this fix:
- Sending invalid address data would return 500 Internal Server Error
- The error occurred during validation building phase

### After this fix:
- Invalid address data now returns 400 Bad Request with proper JSON validation error response
- Billing address must be an associative array when provided
- Shipping address can be either an associative array or null
- Clear validation messages indicate exactly what field has the wrong type

This ensures consistent error handling and better developer experience when integrating with the registration API.
