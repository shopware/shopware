---
title: Add warning state to sw-tabs-item
issue: NEXT-25231
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Administration
* Added property `hasWarning: Boolean` to `src/app/component/base/sw-tabs-item/index.js` (defaults to `false`)
* Added property `errorTooltip: String` to `src/app/component/base/sw-tabs-item/index.js` (defaults to `global.sw-tabs-item.tooltipTabHasErrors`)
* Added property `warningTooltip: String` to `src/app/component/base/sw-tabs-item/index.js` (defaults to `global.sw-tabs-item.tooltipTabHasWarnings`)
* Changed computed property `tabsItemClasses` in `src/app/component/base/sw-tabs-item/index.js` to add the `sw-tabs-item--has-warning` class if `hasWarning` is `true`
* Added computed property `activeTabHasWarnings` to `src/app/component/base/sw-tabs/index.js`
* Changed computed property `sliderClasses` in `src/app/component/base/sw-tabs/index.js` to add the `has--warning` class if `activeTabHasWarnings` is `true`
* Added watcher for property `activeTabHasWarnings` in `src/app/component/base/sw-tabs/index.js` to recalculate the slider  
