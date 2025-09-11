---
title: Fix password field autocomplete with the `new-password` type
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `autocomplete` for `sw-users-permissions` & `sw-profile` pages from `off` to `new-password` for password fields where there purpose is to insert a new password (allows a password manager to suggest a new password)
