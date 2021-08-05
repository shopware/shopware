---
title: Duplicate rule should reset created_at
issue: NEXT-15733
---
# Administration
* Changed method `onDuplicate` in `sw-settings-rule-list` component to overwrite the `createdAt` property
* Changed method `onDuplicate` in `sw-settings-rule-list` component to add a suffix to the rule title
* Added snippet `global.default.copy`
* Removed snippet `sw-product.general.copy`
