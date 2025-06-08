---
title: Save changes modal at language switch is saving the data in the wrong language
issue: NEXT-13391
---
# Administration
* Changed method `onSave` in `src/module/sw-category/page/sw-category-detail/index.js` to change `languageId` before saving category.
