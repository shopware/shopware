---
title: Improve admin last activity behavior
issue: NEXT-00000
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Administration
* Added `refreshTokenTtl` parameter to the construct of `AdministrationController`
* Changed `services.xml` to include the `%shopware.api.refresh_token_ttl%` parameter
* Changed `app/state/context.store.ts` to include `refreshTokenTtl` in the api context
* Changed `views/administration/index.html.twig` to include `refreshTokenTtl` in the api context
* Added `getLastUserActivity` in `service/user-activity.service.ts` to get the last user activity date
* Changed `updateLastUserActivity` in `service/user-activity.service.ts` to use the global `localStorage` instead of the `cookieStorage`
* Changed `setBearerAuthentication` in `service/login.service.ts` to correctly update the expire time with the remember me feature
* Changed `loginByUsername` in `service/login.service.ts` to use the `userActivityService` to call `updateLastUserActivity`
* Changed `lastActivityOverThreshold` in `service/login.service.ts` to use the `userActivityService` to call `getLastUserActivity`
* Changed `forwardLogout` in `service/login.service.ts` to use the global `sessionStorage` instead of the `cookieStorage`
* Changed `setRememberMe` in `service/login.service.ts` to change the `rememberMe` value from a timestamp to true/false
* Changed `shouldConsiderUserActivity` in `service/login.service.ts` to check for the bool state of the `rememberMe` value
* Changed `addGlobalNavigationGuard` in `factory/router.factory.js` to use the `userActivityService` to call `updateLastUserActivity`
* Changed `beforeMount` in `sw-login/page/index/index.js` to correctly check the `refresh-after-logout` sessionStorage key
___
# Core
* Changed `setLastActivity` in `shop-configuration.html.twig` to correctly update the `lastActivity` localStorage key
