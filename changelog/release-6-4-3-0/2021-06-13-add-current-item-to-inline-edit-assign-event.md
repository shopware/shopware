---
title: Add current item to inline-edit-assign event of Data Grid component
issue: NEXT-15713
author: Cuong Huynh
author_email: cuongdev@hotmail.com
author_github: @cuonghuynh
---
# Core
* Changed method `onClickSaveInlineEdit` in `src/Administration/Resources/app/administration/src/app/component/data-grid/sw-data-grid/index.js` to assign current item to `inline-edit-assign` event, we can access the selected item before sending save request to Shopware.
