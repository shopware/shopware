---
title: Improve clickabilty of sw-tabs 
issue: NEXT-18101
author: Enzo Volkmann
author_email: enzo@exportarts.io
author_github: @evolkmann
---
# Administration
* Improve the clickability of the `sw-tabs` component. When approached with the mouse from the bottom, the
  border that is rendered by the `::before`-style prevented the tab from being clicked. The actual clickable
  area of the tab did not match the visual appearance.
