---
title: Flow builder - add send mail to every event.
issue: NEXT-18665
---
# Core
* Added `MailAware` as an implementation of the following events:
  * `CustomerBeforeLoginEvent`
  * `CustomerChangedPaymentMethodEvent`
  * `CustomerLoginEvent`
  * `CustomerLogoutEvent`
  * `ProductExportLoggingEvent`
