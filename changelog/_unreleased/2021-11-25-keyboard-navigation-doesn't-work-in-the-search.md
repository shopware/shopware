---
title: Keyboard navigation doesn't work in the search
issue: NEXT-18796
flag: FEATURE_NEXT_6040
---
# Administration
* Deprecated 2 methods `navigateLeftResults` and `navigateRightResults` in `src/app/component/structure/sw-search-bar/index.js`. The deprecation will be removed in v6.5.0.0.
* Removed 2 keyboard events `@keydown.left.prevent` and `@keydown.right.prevent` of `sw_search_bar` block in `src/app/component/structure/sw-search-bar/sw-search-bar.html.twig`
