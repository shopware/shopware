---
title: Improve payment handlers & general payment process
issue: NEXT-31047
---
# Core
* Added new `AbstractPaymentHandler` to replace all existing payment handler interfaces
* Deprecated `AsyncPaymentHandlerInterface`, `PreparedPaymentHandlerInterface`, `SyncPaymentHandlerInterface`, `RefundPaymentHandlerInterface`, `RecurringPaymentHandlerInterface`
* Deprecated runtime fields `synchronous`, `asynchronous`, `prepared`, `refund`, `recurring` in `PaymentMethodEntity`
___
# Next Major Version Changes

## Payment: Reworked payment handlers
* The payment handlers have been reworked to provide a more flexible and consistent way to handle payments.
* The new `AbstractPaymentHandler` class should be used to implement payment handlers.
* The following interfaces have been deprecated:
  * `AsyncPaymentHandlerInterface`
  * `PreparedPaymentHandlerInterface`
  * `SyncPaymentHandlerInterface`
  * `RefundPaymentHandlerInterface`
  * `RecurringPaymentHandlerInterface`
* Synchronous and asynchronous payments have been merged to return an optional redirect response.


## Payment: Capture step of prepared payments removed
* The method `capture` has been removed from the `PreparedPaymentHandler` interface.
* Also for apps, this method is no longer being called
* Use `pay` instead

## App System: Payment: payment states
* Previously, for asynchronous payments, the default payment state `unconfirmed` was used for the `pay` call and `paid` for `finalized`.
* This is no longer the case. Payment states are now no longer set by default.

## App system: Payment:  finalize step
* The `finalize` step now transmits the `queryParameters` under the object key `requestData` as other payment calls
