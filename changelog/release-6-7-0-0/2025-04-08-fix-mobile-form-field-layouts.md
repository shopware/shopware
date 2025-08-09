---
title: Fix adjacent form fields on narrow viewports
issue: #7056
---
# Storefront
* Changed `Resources/views/storefront/component/account/login.html.twig` and adjust the following bootstrap grid columns to fix the layout on narrow viewports:
    * Changed `#loginMail` column classes to `col-sm-6 col-lg-12`.
    * Changed `#loginPassword` column classes to `col-sm-6 col-lg-12`.
* Changed `Resources/views/storefront/page/account/profile/index.html.twig` and adjust the following bootstrap grid columns to fix the layout on narrow viewports:
    * Changed `#personalMail` column classes to `col-sm-6`.
    * Changed `#personalMailConfirmation` column classes to `col-sm-6`.
    * Changed `#personalMailPasswordCurrent` column classes to `col-sm-6`.
    * Changed `#newPassword` column classes to `col-sm-6`.
    * Changed `#newPasswordConfirmation` column classes to `col-sm-6`.