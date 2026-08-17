---
title: Prevent newsletter double opt-in bypass via confirmSubscribe option
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @larskemper
---
# Core
* Changed `Shopware\Core\Content\Newsletter\SalesChannel\NewsletterSubscribeRoute` so the `confirmSubscribe` option of the `store-api.newsletter.subscribe` route now respects the double opt-in configuration. When double opt-in is enabled, `confirmSubscribe` stores the recipient as pending (`notSet`) instead of directly activating it (`optIn`). Activating a subscription still requires the `store-api.newsletter.confirm` route with a valid hash and `em` proof.
