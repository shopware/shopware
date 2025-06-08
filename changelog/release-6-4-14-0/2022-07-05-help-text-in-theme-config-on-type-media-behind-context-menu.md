---
title: Help Text in theme config on "type": "media" is behind context menu
issue: NEXT-20611
---
# Storefront
* Changed in `src/Storefront/Resources/app/administration/src/modules/sw-theme-manager/page/sw-theme-manager-detail/sw-theme-manager-detail.scss`
    * Removed position of `.sw-media-upload-v2__switch-mode` in `sw-theme-manager-detail.scsss` to bring the three dot menu back to the document flow
    * Added height and adjusted top and bottom margin of `.sw-media-upload-v2__header` to align the help text and the menu both with the label of field
