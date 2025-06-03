---
title: Only consider product rule ids in HTTP cache key generation
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Changed the content of the context cache key, stored in the cookie `sw-cache-hash`, to only consider rules which are relevant for product prices
* Deprecated the unused constants `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas::{CATEGORY_AREA,LANDING_PAGE_AREA}`
___
# Upgrade Information
## Only rules relevant for product prices are considered in the `sw-cache-hash`
The cookie `sw-cache-hash` will only contain rule ids which are used to alter product prices, in contrast to previous all active rules, which might only be used for a promotion. If the content changes depending on a rule, the corresponding rule ids should be added using the `Shopware\Core\Framework\Adapter\Cache\Event\HttpCacheCookieEvent`.

You can also add custom rule areas using the flag `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas` on a rule association.

## Removed unused `RuleAreas` constants
The constants `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas::{CATEGORY_AREA,LANDING_PAGE_AREA}` are not used anymore and will therefore be removed
