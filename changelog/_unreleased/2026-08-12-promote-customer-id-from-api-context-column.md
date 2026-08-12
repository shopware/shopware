---
title: Promote customer_id from sales_channel_api_context column when payload omits customerId
author: Konstantin
author_email: k@componentk.eu
author_github: augsteyer
---
# Core
* Changed `SalesChannelContextPersister::load` to set `customerId` in the returned session payload when the `customer_id` database column is populated but the JSON payload does not contain `customerId`, so `SalesChannelContextFactory` hydrates the customer entity on subsequent Store API requests (e.g. after `replace()` inserted an empty payload).
