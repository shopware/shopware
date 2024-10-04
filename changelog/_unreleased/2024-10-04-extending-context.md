---
title: Extending context
issue: 
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
author_github: OliverSkroblin
---
# Core
* Deprecated getter functions of `\Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent::getSalesChannelContext`
* Changed visibility of `\Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent` variables to public
* Added `\Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent::$session` variable
* Added `\Shopware\Core\System\SalesChannel\Event\SwitchContextEvent` event to allow more context variables to be persisted
* Removed `cache_rework` feature flag condition for `\Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent` event.
___
# Upgrade Information
## SalesChannelContextCreatedEvent
- Search for `\Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent::getSalesChannelContext`
  - replace with `$event->context`
- Search for `\Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent::getContext`
  - replace with `$event->context->getContext()`
- Search for `\Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent::getUsedToken`
  - replace with `$event->usedToken`
