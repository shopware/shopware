---
title: Fix wrong customer context on login if entry from sales_channel_api_context is expired
issue: 11097
author: Christoph Pötz
author_github: @acris-cp
---
# Core
* Changed `Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister` so it doesn't remove `customerId` and other information from payload if the context is expired
