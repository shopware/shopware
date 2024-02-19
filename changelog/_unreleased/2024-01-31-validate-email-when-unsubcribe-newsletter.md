---
title: Validate email when unsubcribing newsletter
issue: NEXT-33423
---
# Core
* Changed `\Shopware\Core\Content\Newsletter\SalesChannel\NewsletterUnsubscribeRoute::unsubscribe` to add a check if email parameter is valid.
