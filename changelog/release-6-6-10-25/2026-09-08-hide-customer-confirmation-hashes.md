---
title: Webhook payloads no longer expose sensitive flow data
---
# Core
* Changed `user.recovery.request`, `customer.recovery.request`, `mail.before.send`, and `mail.after.create.message` to no longer be delivered to webhooks. They remain available in Flow Builder and existing app subscriptions stay valid.
* Changed the `mail.sent` webhook payload to no longer include rendered mail contents. Its subject and recipients remain available, and all event data remains available in Flow Builder.
* Changed webhook payloads to no longer include the `contextToken` of `checkout.customer.login`, the `confirmUrl` of `checkout.customer.double_opt_in_registration` and `checkout.customer.double_opt_in_guest_order`, or the `url` of `newsletter.register`. These values remain available in Flow Builder and mail templates.
* Added `EventDataCollection::HIDDEN_FROM_WEBHOOK` for event authors to keep a declared event value available to Flow Builder while excluding it from webhook payloads.
* Changed customer registration confirmation hashes to no longer be included in API responses or webhook customer payloads. The internal registration confirmation flow is unchanged.
