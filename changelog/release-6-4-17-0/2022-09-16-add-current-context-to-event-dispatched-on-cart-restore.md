---
title: Add current SalesChannelContext to the SalesChannelContextRestoredEvent
issue: NEXT-23274
author: stuzzo
author_email: stuzzo@gmail.com
author_github: stuzzo
---
# Core
* Changed the `Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent` to also return the current context in addition to the restored one
* Deprecated `Shopware\Core\System\SalesChannel\Event\SalesChannelContextRestoredEvent::__construct()` because the `$currentContext` parameter will be mandatory 
