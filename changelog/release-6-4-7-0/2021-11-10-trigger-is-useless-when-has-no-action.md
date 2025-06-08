---
title: Trigger is useless when has no action
issue: NEXT-17781
---
# Administration
* Change `styling` computed property in `src/app/component/tree/sw-tree-item/index.js` to add new class `is--disabled` for disabled status of item.
* Added `v-tooltip` directive in `src/app/component/tree/sw-tree-item/sw-tree-item.html.twig` to show tooltip popup if `item.tooltipText` has value.
* Added new property `disabled, toolTipText` into item of tree in `/src/app/component/tree/sw-tree/index.js`.
* Changed `handleClickEvent` method in `/src/module/sw-flow/component/sw-flow-trigger/index.js` to prevent closing dropdown when clicking on disabled item.
* Changed `changeTrigger` method in `/src/module/sw-flow/component/sw-flow-trigger/index.js` to check early return.
* Added `hasOnlyStopFlow` method in `/src/module/sw-flow/component/sw-flow-trigger/index.js`.
* Changed `getEventTree` method in `/src/module/sw-flow/component/sw-flow-trigger/index.js` for adding new property of tree item.
