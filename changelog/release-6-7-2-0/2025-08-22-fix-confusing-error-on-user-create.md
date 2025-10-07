---
title: Fix confusing error on user create in admin
issue: #12040
---
# Administration
* Changed `sw-users-permissions-user-detail` component, to only reauthenticate user with the new password if the changed user is the actually logged-in user, thus fixing an error when creating new users.
