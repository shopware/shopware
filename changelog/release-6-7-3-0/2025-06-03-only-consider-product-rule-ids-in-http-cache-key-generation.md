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
## (Opt-in) Only rules relevant for product prices are considered in the `sw-cache-hash`
**This functionality will become the default with 6.8, you can opt-in by activating the `CACHE_CONTEXT_HASH_RULES_OPTIMIZATION` feature flag.**

In the default Shopware setup the `sw-cache-hash` cookie will only contain rule ids which are used to alter product prices, in contrast to previous all active rules, which might only be used for a promotion.

If the Storefront content changes depending on a rule, the corresponding rule ids should be added using the extension `Shopware\Core\Framework\Adapter\Cache\Http\Extension\ResolveCacheRelevantRuleIdsExtension`. In the extension it is either possible to add specific rule ids directly or add them to the `ResolveCacheRelevantRuleIdsExtension::ruleAreas` array directly, i.e.

```php
class ResolveRuleIds implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ResolveCacheRelevantRuleIdsExtension::NAME . '.pre' => 'onResolveRuleAreas',
        ];
    }

    public function onResolveRuleAreas(ResolveCacheRelevantRuleIdsExtension $extension): void
    {
        $extension->ruleAreas[] = RuleExtension::MY_CUSTOM_RULE_AREA;
    }
}
```

If some custom entity has a relation to a rule, which might alter the storefront, you should add them to either an existing area, or your own are using the DAL flag `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas` on the rule association.

## Deprecated unused `RuleAreas` constants
The constants `Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\RuleAreas::{CATEGORY_AREA,LANDING_PAGE_AREA}` are not used anymore and are now deprecated and will therefore be removed in 6.8.
