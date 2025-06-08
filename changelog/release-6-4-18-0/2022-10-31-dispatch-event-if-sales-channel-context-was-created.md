---
title: Dispatch event if sales channel context was created
issue: NEXT-23355
author: Martin Krzykawski
author_email: m.krzykawski@shopware.com
---
# Core
* Added `Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent` that will be dispatched in `Shopware\Core\System\SalesChannel\Context\SalesChannelContextService::get` if a `Shopware\Core\System\SalesChannel\SalesChannelContext` was created.
