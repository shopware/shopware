---
title: deprecate use of shopware.usage_data.dispatch_enabled and replace it with more fine granular options
issue: ANA-217
author: Moritz Krafeld
---
# Core
* Deprecated `shopware.usage_data.dispatch_enabled` configuration. 
* Added `shopware.usage_data.collection_enabled` configuration as a more fine granular replacement for the deprecated configuration `shopware.usage_data.dispatch_enabled` to prevent starting the collection process. This also stops of keeping track of deleted entities.
* Added `shopware.usage_data.entity_dispatch_enabled` configuration as a more fine granular replacement for the deprecated configuration `shopware.usage_data.dispatch_enabled` to prevent dispatching of entities.
* Added `shopware.usage_data.consnet_dispatch_enabled` configuration as a more fine granular replacement for the deprecated configuration `shopware.usage_data.dispatch_enabled` to prevent dispatching of the consent state.
___
# Next Major Version Changes
## Removal of shopware.usage_data.dispatch_enabled
Prior to shopware 6.8.0, the configuration `shopware.usage_data.dispatch_enabled` was used to enable or disable the dispatching of usage data.  
This configuration has been deprecated and will be removed in shopware 6.8.0. Instead, the dispatching of usage data can be controlled more granular by using the following configurations:
* `shopware.usage_data.collection_enabled` to prevent starting the collection process. This also stops of keeping track of deleted entities.
* `shopware.usage_data.entity_dispatch_enabled` to prevent dispatching of entities.
* `shopware.usage_data.consnet_dispatch_enabled` to prevent dispatching of the consent state.
