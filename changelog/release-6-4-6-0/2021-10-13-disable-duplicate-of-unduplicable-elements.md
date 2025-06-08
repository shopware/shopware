---
title: Disable duplicate of unduplicable elements
issue: NEXT-16456
---
# Administration
* Changed behavior of CMS detail page:
  * Blocks aren't duplicable anymore, when they're considerd "unique" on Product Detail Page layout
  * When those blocks are removed and the Missing Element Modal triggers, decling to save will reload the page to get those removed blocks back
* Added `duplicable` parameter to `sw-cms-sidebar-nav-element`