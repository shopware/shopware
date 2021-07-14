---
title: Replace sidebar media component by media modal
issue: NEXT-15948
flag: FEATURE_NEXT_6040
---
# Administration
* Changed watcher `user.avatarMedia` to `user.avatarMedia.id` in `src/module/sw-profile/page/sw-profile-index/index.js` to fix the issue regarding preview avatar when changed new avatar.
* Changed method `createdComponent` in `src/module/sw-profile/page/sw-profile-index/index.js` to get media default folder.
* Changed method `saveUser` in `src/module/sw-profile/page/sw-profile-index/index.js` to check refs `mediaSidebarItem` exist.
* Changed block `sw_profile_index_router_view` in `src/module/sw-profile/page/sw-profile-index/sw-profile-index.html.twig` to update event `@media-open` to open media modal instead of media sidebar.
* Changed block `sw_profile_index_sidebar` in `src/module/sw-profile/page/sw-profile-index/sw-profile-index.html.twig`.
* Added the methods in `src/module/sw-profile/page/sw-profile-index` module:
    * `openMediaModal`
    * `onMediaSelectionChange`.
    * `getMediaDefaultFolderId` to get media default folder Id.
* Added component `sw-media-modal-v2` in `src/module/sw-profile/page/sw-profile-index/sw-profile-index.html.twig` to replace `sw-sidebar` component.
* Deprecated component `sw-sidebar` in favor for `sw-media-modal-v2` which can only be accessed using feature flag `FEATURE_NEXT_6040`. The deprecation will be removed in v6.5.0.0.
    * The following methods got deprecated in `src/module/sw-profile/page/sw-profile-index` module:
        * `setMediaFromSidebar`
        * `openMediaSidebar`
    * The following refs got deprecated in `src/module/sw-profile/page/sw-profile-index` module:
        * `mediaSidebarItem`
