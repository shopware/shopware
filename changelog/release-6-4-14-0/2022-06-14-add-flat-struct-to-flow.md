---
title: Add Flat struct to flow
issue: NEXT-21089
---
# Core
* Added `flat` protected in `Shopware\Core\Content\Flow\Dispatching\Struct\Flow`.
* Added `getFlat` and `jump` public functions in `Shopware\Core\Content\Flow\Dispatching\Struct\Flow`.
* Changed `build`, `createNestedSequence`, `createNestedAction` and `createNestedIf` functions in `Shopware\Core\Content\Flow\Dispatching\FlowBuilder` to build the flat struct for flow payload.
