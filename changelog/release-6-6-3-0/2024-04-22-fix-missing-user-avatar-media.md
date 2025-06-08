---
title: Fix missing user avatar media
issue: NEXT-27720
author: Benjamin Wittwer
author_email: dev@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `UserController` to add the missing `avatarMedia` association
___
# Administration
* Changed `sw-profile-index` to load the missing user avatar on page load
* Changed `sw-users-permissions-user-listing` to add the missing `avatarMedia` association
* Changed `sw-users-permissions-user-detail` to load the missing user avatar on page load & provide media modal methods
* Changed `sw-users-permissions-user-detail` twig template to include media modal content
