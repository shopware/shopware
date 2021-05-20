---
title: Sort by role empties table
issue: NEXT-13699
author: Niklas Limberg
author_email: n.limberg@shopware.com
author: NiklasLimberg
author_github: NiklasLimberg
---
# Administration
* Added the `sortable: false` flag to the `aclRoles` column definition in the `sw-users-permissions-user-listing`, to prevent sorting by `aclRole` which is not supported by the backend.
