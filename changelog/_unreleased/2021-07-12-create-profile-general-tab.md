---
title: Create profile general tab
issue: NEXT-15904
flag: FEATURE_NEXT_6040
---
# Administration
* Added the following components in `sw-profile` module:
    * `sw-profile-index-general`
    * `sw-profile-index-search-preferences`
* Added the following routes in `sw-profile` module:
    * `sw.profile.index.general`
    * `sw.profile.index.searchPreferences`
* Added the following methods in `sw-profile-index` component:
    * `onChangeNewPassword`
    * `onChangeNewPasswordConfirm`
* Added the following blocks in `sw-profile-index` component template:
    * `sw_profile_index_tabs`
    * `sw_profile_index_router_view`
* Deprecated `sw_profile_index_content` block in `sw-profile-index` component template.
