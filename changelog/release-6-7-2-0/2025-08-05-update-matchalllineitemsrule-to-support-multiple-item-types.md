---
title: Update MatchAllLineItemsRule to support multiple item types
issue: 10720
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @larskemper
---
# Core
* Changed `Shopware\Core\Framework\Rule\Container\MatchAllLineItemsRule` to support multiple line item types by changing the `$type` property and its corresponding constraint to an `array` and renaming the property to `$types`.
