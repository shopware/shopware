---
title: Add aria-labelledby for offcanvas filter
issue: NEXT-40818
author: Björn Meyer
author_email: b.meyer@shopware.com
author_github: @BrocksiNet
---
# Storefront
* Changed `offcanvas-filter.plugin.js` to create a virtual wrapper div that is added to the OffCanvas filter template.
* Added `aria-labelledby` to `cms-element-sidebar-filter.html.twig` template.
* Removed some useless SCSS rules like `filter-panel-wrapper` and `.btn.filter-panel-wrapper-toggle`.

These changes will improve the accessibility of the filter panel in the OffCanvas.
