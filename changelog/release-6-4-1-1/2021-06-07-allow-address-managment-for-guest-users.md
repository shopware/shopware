---
title: Allow address management for guest users
issue: NEXT-14585
---
# API
* Allowed following routes are now able to be used by guest users:
    - DELETE `/store-api/account/address/{addressId}`
    - DELETE `/store-api/account/customer`
    - POST `/store-api/account/list-address`
    - POST `/store-api/account/logout`
    - POST `/store-api/account/address`
    - PATCH `/store-api/account/address/{addressId}`
