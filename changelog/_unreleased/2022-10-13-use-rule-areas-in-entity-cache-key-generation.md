---
title: Use rule areas in entity cache key generation
issue: NEXT-23135
flag: V6_5_0_0
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Core
* Added `RuleAreas` flag for use on associations of `RuleDefinition`
* Added `areas` list field to `RuleDefinition`
* Added `RuleAreaUpdater` to update `rule.area` by existence of associations featuring the `RuleAreas` flag
* Added `RuleCollection::getIdsByArea` to retrieve an array of used areas with the corresponding IDs
* Added `SalesChannelContext::getRuleIdsByAreas` to retrieve currently active rule IDs by a list of areas
___
# Next Major Version Changes
## Cache key hash generation by rule areas
The method `EntityCacheKeyGenerator::getSalesChannelContextHash` will use the second argument, a list of areas, to retrieve a list of active rule IDs belonging to these areas. Instead of all active rule IDs, only the filtered IDs will then be used for generating the hashed cache key value. The cached routes call the method with their corresponding areas to retrieve a cache key which isn't affected by active rules used in other contexts.
