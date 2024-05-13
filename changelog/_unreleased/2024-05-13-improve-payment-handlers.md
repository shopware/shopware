---
title: Improve payment handlers & general payment process
issue: NEXT-31047
---
# Core
* Added new `AbstractPaymentHandler` to replace all existing payment handler interfaces
* Deprecated `AsyncPaymentHandlerInterface`, `PreparedPaymentHandlerInterface`, `SyncPaymentHandlerInterface`, `RefundPaymentHandlerInterface`, `RecurringPaymentHandlerInterface`
___
# Next Major Version Changes
## Prepared payments: Capture removed
* The method `capture` has been removed from the `PreparedPaymentHandler` interface.
* Also for apps, this method is no longer being called
* Use `pay` instead

## App payment states
* Previously, for asynchronous payments, the default payment state `unconfirmed` was used for the `pay` call and `paid` for `finalized`.
* This is no longer the case. Payment states are now no longer set by default.

## App payment finalize step
* The `finalize` step now transmits the `queryParameters` under the object key `requestData` as other payment calls
