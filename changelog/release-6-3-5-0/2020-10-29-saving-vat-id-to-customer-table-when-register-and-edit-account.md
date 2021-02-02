---
title: Saving VAT ID to customer table when register and edit account
issue: NEXT-11193
___
# Core
*  Added `vatIds` from the request data into `customer` when update customer profile in `src/Core/Checkout/Customer/SalesChannel/ChangeCustomerProfileRoute`.
*  Added `vatIds` from the request data into `customer` when register a customer in `src/Core/Checkout/Customer/SalesChannel/RegisterRoute`.
