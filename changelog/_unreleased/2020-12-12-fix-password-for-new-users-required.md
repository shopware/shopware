---
title: Fix password field for new users is require
issue: NEXT-12268
---
# Administration
* Added computed `mapPropertyErrors` in `src/module/sw-users-permissions/page/sw-users-permissions-user-create/index.js` to handle map property errors.
* Added attribute `required` for the `sw_settings_user_detail_content_password` block in `sw-users-permissions-user-create.html.twig` to set required password field.
* Added attribute `:error="userPasswordError"` for the `sw_settings_user_detail_content_password` block in `sw-users-permissions-user-create.html.twig` to handle error valid.
* Added attribute `:error="userFirstNameError"` for the `sw_settings_user_detail_content_first_name` block in `sw-users-permissions-user-detail.html.twig` to handle error valid.
* Changed directive `v-model.trim` for the `sw_settings_user_detail_content_email` block in `sw-users-permissions-user-detail.html.twig`.
* Changed method `saveUser` in `src/module/sw-users-permissions/page/sw-users-permissions-user-detail/index.js` returns a Promise that is rejected.
