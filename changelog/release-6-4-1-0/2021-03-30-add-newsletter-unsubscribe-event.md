---
title: Add newsletter.unsubscribe Event
issue: NEXT-14540
---
# Core
* Added `\Shopware\Core\Content\Newsletter\Event\NewsletterUnsubscribeEvent`
* Changed `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute` to dispatch `NewsletterUnsubscribeEvent`
* Deprecated `\Shopware\Core\Content\Newsletter\Event\NewsletterUpdateEvent` it will be removed in 6.5.0.0 as it was never thrown.
