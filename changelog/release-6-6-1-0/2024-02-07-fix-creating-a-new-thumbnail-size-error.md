---
title: Fix creating a new thumbnail size error
issue: NEXT-33571
---
# Administration
* Deprecated the following properties and methods in `sw-media-modal-folder-settings` component:
    * `isEditThumbnails`
    * `thumbnailListClass`
    * `labelToggleButton`
    * `toggleEditThumbnails`
* Deprecated the following blocks in `sw-media-modal-folder-settings` component template:
    * `sw_media_modal_folder_settings_edit_thumbnail_list_button`
    * `sw_media_modal_folder_settings_add_thumbnail_size_form`
* Changed the following methods in `sw-media-modal-folder-settings` component:
    * `addThumbnail`
    * `checkIfThumbnailExists`
