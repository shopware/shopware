---
title: Apply fixes in user permissions
issue: NEXT-33338
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added `isEmailAlreadyInUse` to `sw-users-permission-user-detail`
* Changed deprecated `isEmailUsed` in `sw-users-permission-user-detail`
* Changed removed unnecessary `key` in `sw-users-permissions-additional-permissions`
___
# Next Major Version Changes
## Replace `isEmailUsed` with `isEmailAlreadyInUse`:
* Replace `isEmailUsed` with `isEmailAlreadyInUse` in `sw-users-permission-user-detail`.
