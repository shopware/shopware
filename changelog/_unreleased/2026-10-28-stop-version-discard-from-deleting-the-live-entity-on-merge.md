---
title: Stop version discard from deleting the live entity on merge
issue: 20142
---

# API

* Changed `ApiController::deleteVersion()` to discard the draft with `VersionManager::DISABLE_AUDIT_LOG`, so the discard no longer records a `delete` action in the version change set.
* Changed `ApiController::deleteVersion()` to remove the `version_commit` rows of the version before the `version` row.
  A merge of a discarded version now fails with `FRAMEWORK__VERSION_NO_COMMITS_FOUND` and no longer changes the live entity.

# Administration

* Changed `sw-order-detail` to release the order version before it calls `mergeVersion()`, so leaving the page during a save no longer discards the version that is being merged.
