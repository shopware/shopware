---
title: Limit maximum length for search term
issue: NEXT-30264
---
# Administration
* Changed `onSearchTermChange` method in `sw-search-bar` component to prevent calling the search service when the search term reaches the maximum length
