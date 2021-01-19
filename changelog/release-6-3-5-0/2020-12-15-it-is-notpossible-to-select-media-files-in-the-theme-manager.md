---
title: It is not possible to select media files in the theme manager
issue: NEXT-12295
---
# Administration
* Added new `sw_theme_manager_detail_sidebar_media_items` block in `sw-theme-manager-detail.html.twig` to add media items from the sidebar. It will automatically generate all the "Add to media" buttons for the different media type fields declared in the `config.fields` of the `theme.json` file.
* Added new method `onAddMediaToTheme` in `sw-theme-manager-detail/index.js`

