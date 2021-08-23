---
title: Remove usage of JSON_EXTRACT in SalesChannelContextPersister
issue: NEXT-13781
author: Patrick Stahl
author_email: p.stahl@shopware.com 
author_github: @PaddyS
---
# Core
* Changed `SalesChannelContextPersister` to use the `customer_id` column directly instead of using `JSON_EXTRACT` on the `payload` column
* Added migration `Migration1627993629FixCustomerIdInSalesChannelContext` to automatically fix outdated `NULL` entries in the `sales_channel_api_context` table 
