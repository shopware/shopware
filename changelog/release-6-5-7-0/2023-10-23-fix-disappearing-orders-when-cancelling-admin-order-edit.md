---
title: Fixing edge case where orders would disappear when cancelling admin order edit
issue: NEXT-23915
---

# Core
* Changed `VersionManager` to not allow merging of non-existing versions and versions without commits.
___
# Administration
* Changed version loading in `onCancelEditing` in `sw-order-detail`
