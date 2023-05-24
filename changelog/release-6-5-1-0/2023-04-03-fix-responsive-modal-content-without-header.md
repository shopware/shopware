---
title: Fix responsive modal content without header
issue: NEXT-25553
author: Fabian Hüske
---
# Administration
* Changed `sw-modal` to apply `has--header` class when option `showHeader` is true to fix modal content not expanding in height on mobile viewports.
* Deprecated computed property `identifierClass` in `src/app/component/base/sw-modal/index.js`. The property will be removed.
