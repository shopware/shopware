---
title: Allow locked product sorting translation
issue: NEXT-00000
author: Elias Lackner
author_email: lackner.elias@gmail.com
author_github: @lacknere
---
# Core
* Added `lockTranslation` parameter and getter to `LockedField` class for allowing unlock of the corresponding translation entity.
* Added `isTranslationUpdate` method to `LockValidator` and changed `containsLockedEntities` to identify and allow a translation only update in case of unlocked translation entity.
___
# Administration
* Changed `sw-settings-listing-option-criteria-grid.html.twig` to disable criteria selection and manipulation for locked product sortings.
* Changed `sw-settings-listing-option-general-info.html.twig` to disable untranslated fields for locked product sortings.
* Removed unused `isProductSortingEditable` method and `locked` filter in product sorting option criterias from `sw-settings-listing` component.
* Added methods `allowProductSortingOptionDelete` and `getProductSortingOptionDeleteTooltip` methods to `sw-settings-listing` component.
* Changed `fetchProductSortingEntity` method of `sw-settings-listing-option-base` component to get the product sorting without the unnecessary computed `productSortingEntityCriteria` which has been removed from the component.
* Deprecated block `sw_settings_listing_option_base_smart_content` in `sw-settings-listing-option-base.html.twig`. Use `sw_settings_listing_option_base_content` instead.
* Deprecated block `sw_settings_listing_option_base_smart_content_general_info` in `sw-settings-listing-option-base.html.twig`. Use `sw_settings_listing_option_base_content_general_info` instead.
* Deprecated block `sw_settings_listing_option_base_smart_bar_actions_grid` in `sw-settings-listing-option-base.html.twig`. Use `sw_settings_listing_option_base_content_criteria_grid` instead.
* Deprecated block `sw_settings_listing_option_base_smart_bar_actions_grid_delete_modal` in `sw-settings-listing-option-base.html.twig`. Use `sw_settings_listing_option_base_content_delete_modal` instead.
* Added new block `sw_settings_listing_option_base_content_locked_info` to `sw-settings-listing-option-base.html.twig`.
* Changed snippet value of `sw-settings-listing.general.productSortingCriteriaGrid.options.label._score`.
* Added snippets:
    - `sw-settings-listing.index.productSorting.grid.deleteTooltip.locked`
    - `sw-settings-listing.index.productSorting.grid.deleteTooltip.default`
    - `sw-settings-listing.base.lockedInfo`
