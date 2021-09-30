---
title: Create search preferences modal
issue: NEXT-15986
flag: FEATURE_NEXT_6040
---
# Administration
* Added `sw-search-preferences-modal` component in `src/app/component/modal`.
* Added `showSearchPreferencesModal` data variable in `sw-search-bar` component.
* Added `toggleSearchPreferencesModal` method in `sw-search-bar` component.
* Changed the following blocks in `sw-search-bar` component template:
    * `sw_search_bar_results_footer`
    * `sw_search_bar_types_container_footer`
* Added `sw_search_bar_search_preferences_modal` block in `sw-search-bar` component template to show the modal if needed.
* Changed `sw-profile-index-search-preferences` component to update search preferences data if needed.
