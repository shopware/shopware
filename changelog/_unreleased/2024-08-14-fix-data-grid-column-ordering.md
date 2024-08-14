---
title: Fix data-grid column ordering
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Administration
* Changed `onClickChangeColumnOrderUp` and `onClickChangeColumnOrderDown` methods of `sw-data-grid-settings` component to recieve `column` instead of `index` as parameter. The column index is evaluated from `currentColumns` now.
