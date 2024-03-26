---
title: Add pagination in media selection
issue: NEXT-31040
---
# Administration
* Added the following data variables in `sw-media-field` component:
    * `page`
    * `limit`
    * `total`
* Changed the following computed properties and methods in `sw-media-field` component to update the new data variables:
    * `suggestionCriteria`
    * `onSearchTermChange`
    * `fetchSuggestions`
    * `onTogglePicker`
* Added `onPageChange` method in `sw-media-field` component to handle the `page` and `limit` change
* Added `sw-pagination` component in `sw-media-field` component template to allow state transitions
