---
title: Fix essential characteristics create template with more than 2 values does not work
issue: 10190
---
# Administration
* Added computed `addButtonDisabled` in `sw-settings-product-feature-sets-modal` component to replace the static addButtonDisabled property with a computed property that dynamically evaluates whether the button should be disabled based on the current selection context.
* Added method `applySelectionsToActiveGrid` in `sw-settings-product-feature-sets-modal` component to apply preselected items to the currently active grid.
* Changed `getCustomFieldList` and `getPropertyList` in `sw-settings-product-feature-sets-modal` component to call applySelectionsToActiveGrid after loading data.
