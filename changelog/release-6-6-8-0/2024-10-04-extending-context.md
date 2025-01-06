---
title: Extending context
issue: NEXT-38856
author: Oliver Skroblin
author_email: oliver@goblin-coders.de
author_github: OliverSkroblin
---
# Core
* Added `\Shopware\Core\System\SalesChannel\Event\SalesChannelContextCreatedEvent::$session` variable.
* Added `\Shopware\Core\System\SalesChannel\Event\SwitchContextEvent` event to allow more context variables to be persisted.
* Removed `cache_rework` feature flag condition for `\Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent` event.
