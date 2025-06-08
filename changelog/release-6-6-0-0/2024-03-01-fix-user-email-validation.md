---
title: Fix user email validation
issue: NEXT-34114
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Core
* Changed `UserDefinition` field `email` from type `StringField` to `EmailField`.
___
# Administration
* Changed `sw-users-permissions-user-detail` to no longer validate for valid email format as the API will do that.
