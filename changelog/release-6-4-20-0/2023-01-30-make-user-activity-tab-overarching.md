---
title: Make user activity tab overarching
issue: NEXT-24983
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added `sw-inactivity-login`
* Added `html2canvas` npm dependency
* Changed `@shopware-ag/e2e-testsuite-platform` to version 7.0.2
* Changed `user-activity.service.ts` to set cookie in favor of context value
* Changed `context-store.ts` lastActivity to be deprecated
* Changed `app-context.factory.js` to no longer set lastActivity initially
* Changed `router.factory.js` to set lastActivity cookie before each route change
* Changed `login.service.ts` logout to accept 2 parameters: isInactivityLogout & shouldRedirect
* Changed `application.ts` to no longer duplicate redirect to login
* Changed `global.types.ts` currentUser to EntitySchema user
