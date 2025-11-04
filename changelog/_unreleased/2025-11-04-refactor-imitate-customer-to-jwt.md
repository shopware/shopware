---
title: Refactor Imitate Customer logic to JWT
author: Max Stegmeyer
---
# Core
* Changed `Shopware\Core\Checkout\Customer\ImitateCustomerTokenGenerator` to use JWT
* Added `Shopware\Core\Framework\JWT\SalesChannel\JWTGenerator` and `Shopware\Core\Framework\JWT\Struct\JWTStruct` to build general structure for encoding and decoding JWT
