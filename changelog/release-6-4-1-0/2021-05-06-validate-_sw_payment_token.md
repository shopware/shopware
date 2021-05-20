---
title: Validate _sw_payment_token
issue: NEXT-15182
---
# Core
* Changed `/payment/finalize-transaction` to return a `400 BAD REQUEST` if `_sw_payment_token` is empty. It returned a 500 due to a type error before.  
