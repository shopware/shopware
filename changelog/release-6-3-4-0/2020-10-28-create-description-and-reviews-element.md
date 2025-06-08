---
title: Create a new "Description & Reviews" element
issue: NEXT-11555
---
# Administration
*  Added `sw-cms-el-product-description-reviews` component
*  Added `sw-cms-el-config-product-description-reviews` component
*  Added `sw-cms-el-preview-product-description-reviews` component
*  Changed `onSelectElement()` method in `module/sw-cms/component/sw-cms-slot/index.js` to reset locked element props whenever replacing element.
*  Changed `cmsSlotSettingsClasses()` computed property in `module/sw-cms/component/sw-cms-slot/index.js` to handle setting class names for the locked element.
*  Changed `tooltipDisabled()` computed property in `module/sw-cms/component/sw-cms-slot/index.js` to handle enable tooltip for the locked element.
