---
title: Remove deprecations in Core/Framework/Api
issue: NEXT-21203
---
# Core
* Removed all deprecations in `Core/Framework/Api` namespace
* Removed feature flag `FEATURE_NEXT_15815`
* Removed feature flag `FEATURE_NEXT_16151`
* Deprecated class `\Shopware\Core\Framework\Api\Sync\SyncOperationResult`, it will be removed in v6.6.0.0.
___
# Next Major Version Changes
## Removed `SyncOperationResult`
The `\Shopware\Core\Framework\Api\Sync\SyncOperationResult` class was removed without replacement, as it was unused.
