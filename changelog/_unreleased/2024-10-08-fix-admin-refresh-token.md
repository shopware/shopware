---
title: Fix admin refresh token
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Changed `startBootProcess` in `core/application.ts` to call `loginService.refreshToken()` before `bootFullApplication`, if the access token is expired, to ensure the bearer authentication object is valid
* Changed `autoRefreshToken` in `core/service/login.service.ts` to `restartAutoTokenRefresh`
* Changed `restartAutoTokenRefresh` in `core/service/login.service.ts` to always update the timeout for the refresh token when called
* Changed `isLoggedIn` in `core/service/login.service.ts` to just validate the login state, removed the side effect of starting the refresh token update
* Changed `core/service/login.service.ts` to expose `restartAutoTokenRefresh`
