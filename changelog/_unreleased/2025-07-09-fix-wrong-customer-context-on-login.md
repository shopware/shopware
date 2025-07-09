---
title: Fix wrong customer context on login if entry from sales_channel_api_context is expired
issue: 11097
author: Christoph Pötz
author_github: @acris-cp
---
# Core
* `Shopware\Core\System\SalesChannel\Context\SalesChannelContextPersister` don't remove customerId and other information from payload if sales_channel_api_context is expired
