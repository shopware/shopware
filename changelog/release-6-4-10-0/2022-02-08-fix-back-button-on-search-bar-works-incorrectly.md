---
title: Fix back button on search bar works incorrectly
issue: NEXT-19647
---
# Administration
* Added `isComponentMounted` data variable in `sw-search-bar` component.
* Changed `$route` watch property in `sw-search-bar` component to set `currentSearchType` correctly.
* Changed `resetSearchType` method in `sw-search-bar` component to help to set `currentSearchType` correctly when `searchTerm` changes from filled state to empty.
