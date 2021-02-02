---
title: Validate VAT ID
issue: NEXT-11196
---
# Core
* Added `vatIds` from the request data into `customer` when register a customer in `src/Core/Checkout/Customer/SalesChannel/RegisterRoute`.
* Added constraint validator `CustomerVatIdentificationValidator` for customer validation.
* Added validations `NotBlank`, `Type('array')`, `CustomerVatIdentification` for `vatIds` in `src/Core/Checkout/Customer/SalesChannel/RegisterRoute`.
