---
title: Make Order and SalesChannelDomain EntityWrittenEvents hookable
issue: NEXT-16357
---
# Core
* Added `order` and `sales_channel_domain` to the list of hookable entities, thus allowing that apps can subscribe to written and deleted webhooks for those entities.
