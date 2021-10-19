---
title: Add sales channel assignment to theme detail page
issue: NEXT-9448
author: Patrick Stahl
author_email: p.stahl@shopware.com 
author_github: PaddyS
---
# Administration
* Added new property `selectionDisablingMethod` to the components `sw-entity-multi-select` and `sw-select-selection-list` in order to show the disabled state on the labels depending on a given function
___
# Storefront
* Removed `div` with class `sw-theme-manager-detail__info-saleschannels` from `sw-theme-manager-detail.html.twig`
* Removed styles for class `sw-theme-manager-detail__info-saleschannels` from `sw-theme-manager-detail.scss`
* Added new entity selection on the theme detail page in order to be able to assign sales channels directly on the theme detail page
* Added blocks `sw_theme_manager_detail_sales_channel_removed_modal` and `sw_theme_manager_detail_sales_channel_already_assigned_modal` to `sw-theme-manager-detail.html.twig` 
