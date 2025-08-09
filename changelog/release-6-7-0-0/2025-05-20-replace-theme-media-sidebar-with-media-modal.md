---
title: Replace theme media sidebar with media modal
issue: 9055
author: Benedikt Schulze Baek
author_email: benedikt@schulze-baek.de
author_github: @bschulzebaek
---
# Administration
* Changed the theme manager detail page in `src/Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/index.js` to use the `sw-media-modal-v2` instead of the sidebar for media selection. 
  * Deprecated blocks `sw_theme_manager_detail_sidebar`, `sw_theme_manager_detail_sidebar_media` and `sw_theme_manager_detail_sidebar_media_items`. They will be removed in 6.8.0.
  * Deprecated method `openMediaSidebar`. It will be removed in 6.8.0.
