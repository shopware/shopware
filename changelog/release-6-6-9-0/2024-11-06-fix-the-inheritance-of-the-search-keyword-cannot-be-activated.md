---
title: Fix the inheritance of the search keywords cannot be activated
issue: NEXT-37406
author: Thuy Le
author_email: thuy.le@shopware.com
author_github: @thuylt
---
# Administration
* Changed computed `isInherited` in `src/app/component/utils/sw-inherit-wrapper/index.js` to return true when value is an empty array.
* Changed `removeInheritance` method in `src/app/component/utils/sw-inherit-wrapper/index.js` to remove inheritance if the new value is an empty array.
