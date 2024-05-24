---
title: Improve admin login session
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Added a unified `setRememberMe` function to `loginService`
* Changed `sw-inactivity-login` to use the unified `loginService.setRememberMe`
* Changed `sw-login-login` to use the unified `loginService.setRememberMe`
* Changed `loginService` to clear the token refresh timeout on logout
* Changed `loginService` to no longer unnecessary recreate the token refresh timeout
* Changed `setBearerAuthentication` in `loginService` to account for `rememberMe` time and switched from session cookie to a set expire time
