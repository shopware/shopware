---
title: Fix media:delete-unused command crashing on empty batches
issue: 16532
---
# Core
* Changed `Shopware\Core\Content\Media\UnusedMediaPurger::filterOutNewMedia` to return early on empty id arrays, preventing the `media:delete-unused` command from throwing `Invalid ids provided in criteria` when a batch is empty (e.g. when an `UnusedMediaSearchEvent` listener marks every candidate as used or the offset is past the end of the result set) and a grace period is configured.
