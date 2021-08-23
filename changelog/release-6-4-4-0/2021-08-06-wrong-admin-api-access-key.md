---
title: Make sure access key generation for user uses right access point
author: Jisse Reitsma
issue: NEXT-16620
author_email: jisse@yireo.com
author_github: jissereitsma
---
# Administration
* Changed the `generateKey` function in `integration.api.service.js` to use the correct auth endpoint
* Changed the `addAccessKey` function in `sw-users-permissions-user-detail/index.js` to request access key prefixed with `SWUA` from the integration API service

