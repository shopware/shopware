---
title: Add group single select component
issue: NEXT-18627
author: Timo Altholtmann
---
# Administration
* Added new component `sw-grouped-single-select`:
* `src/app/component/form/select/base/sw-grouped-single-select/index.js`
* `src/app/component/form/select/base/sw-grouped-single-select/sw-grouped-single-select.html.twig`
* `src/app/component/form/select/base/sw-grouped-single-select/sw-grouped-single-select.scss`
* Added function `availableGroups` in `src/app/component/rule/sw-condition-tree/index.js`
* Added `translatedLabel` property to all conditions in `availableTypes` in `src/app/component/rule/sw-condition-tree/index.js`
* Changed sorting of conditions in `availableTypes` in `src/app/component/rule/sw-condition-tree/index.js`
* Added `availableGroups` function in `src/app/component/rule/sw-condition-tree/index.js`
* Added property `availableGroups` in `src/app/component/rule/sw-condition-type-select/index.js`
* Deprecated function `translatedTypes` in `src/app/component/rule/sw-condition-type-select/index.js`. The Function is no longer needed, use `translatedLabel` property instead
___
# Next Major Version Changes
* The function `translatedTypes` in `src/app/component/rule/sw-condition-type-select/index.js` is removed. Use `translatedLabel` property of conditions.
```
