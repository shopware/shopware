---
title: Admin users can mark CMS blocks and CMS elements as favorites
issue: NEXT-22617
author: Joshua Behrens
author_email: code@joshua-behrens.de
author_github: @JoshuaBehrens
---
# Administration
* Added new property `expandChevronDirection` to `sw-sidebar-collapse` to control the icon direction for the icon in its collapsed state
* Added new base class `UserConfigClass`, that is an extraction of a base class of the `SalesChannelFavoritesService` class to reduce duplicate code when building other dynamic user configurations
* Added new class `CmsBlockFavoritesService` based on `UserConfigClass` as service by id `cmsBlockFavorites` to store user favourites for cms_block with the configuration key `cms-block-favorites`
* Added new class `CmsElementFavoritesService` based on `UserConfigClass` as service by id `cmsElementFavorites` to store user favourites for cms_block with the configuration key `cms-element-favorites`
* Added new button in `sw-cms-sidebar` within a new twig block `sw_cms_sidebar_block_overview_preview_favorite_action` to toggle user favourites for a cms block
* Added default and dynamic cms block category for favourites. Its content are either entries provided by `cmsBlockFavorites` or an empty state
* Changed display of CMS elements in `sw-cms-slot` by grouping them by their favourite state provided by `cmsElementFavorites` in collapsable panels
* Added new overlay button with a heart icon to entries in `sw-cms-slot` to toggle a CMS elements' favourite state for the current user
