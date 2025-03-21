---
title: Valid date range condition for rule builder
issue: NEXT-37801
---
# Administration
* Added `conditionTreeFlat` computed to recursively collects all conditions from a tree structure in `src/Administration/Resources/app/administration/src/module/sw-settings-rule/page/sw-settings-rule-detail/index.js`.
* Added `validateDateRange` method to checks if all date ranges within the condition tree are valid in `src/Administration/Resources/app/administration/src/module/sw-settings-rule/page/sw-settings-rule-detail/index.js`.
* Changed `onSave` method to validate date ranges and notice an error message before saving a rule in `src/Administration/Resources/app/administration/src/module/sw-settings-rule/page/sw-settings-rule-detail/index.js`.
