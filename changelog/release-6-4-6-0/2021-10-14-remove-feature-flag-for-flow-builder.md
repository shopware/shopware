---
title: Remove feature flag for Flow Builder
issue: NEXT-17397
---
# Core
* Removed feature flag `FEATURE_NEXT_8225`.
* Added a migration class `Shopware\Core\Migration\V6_4\Migration1632215760MoveDataFromEventActionToFlow` to migrate data from `event_action` to `flow` table.
___
# Administration
* Removed feature flag `FEATURE_NEXT_8225`.
