---
title: Prevent CheapestPrice query errors
issue: NEXT-21519
---
# Core
* Added `shopware.dal.max_rule_prices` configuration which limits the number of active rules that are considered for the cheapest price calculation.
* Changed `\Shopware\Core\Content\Product\DataAbstractionLayer\CheapestPrice\CheapestPriceAccessorBuilder` to use the `shopware.dal.max_rule_prices` configuration to limit the number of active rules used in the query, thus preventing errors when many rules are active at once.
___
# Upgrade information
## New `shopware.dal.max_rule_prices` configuration
There is a new configuration option `shopware.dal.max_rule_prices` which limits the number of active rules that are considered for the cheapest price calculation. This is necessary to prevent errors when many rules are active at once. The default value is `100`, which should be sufficient for most use cases. 
You can increase this value to a higher number, but this will negatively impact the performance of you shop. If you have many rules that are active at once, consider restructuring your rules.
