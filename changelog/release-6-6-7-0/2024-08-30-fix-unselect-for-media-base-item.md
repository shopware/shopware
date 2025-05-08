---
title: Fix unselect for media base item
issue: NEXT-37532
---
# Administration
* Changed `sw_media_base_item_selected_indicator` and `sw_media_base_item_list_selected_indicator` block to use `mt-checkbox` component to fix issue related to click event on icon in `src/Administration/Resources/app/administration/src/app/asyncComponent/media/sw-media-base-item/sw-media-base-item.html.twig`.
* Deprecated `sw_media_base_item_selection_indicator_icon` and `sw_media_base_item_list_selection_indicator_icon` block in `src/Administration/Resources/app/administration/src/app/asyncComponent/media/sw-media-base-item/sw-media-base-item.html.twig`.
