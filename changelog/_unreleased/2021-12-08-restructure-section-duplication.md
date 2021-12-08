---
title: Restructure section duplication
issue: NEXT-19052
---
# Administration
*  Added `prepareSectionClone` method to `module/sw-cms/page/sw-cms-detail/index.js`, to split `onSectionDuplicate` into preparing and saving to increase its extensibility