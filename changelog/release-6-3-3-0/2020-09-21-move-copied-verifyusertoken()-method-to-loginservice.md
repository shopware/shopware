---
title: move copied verifyUserToken() method to loginService
issue: NEXT-10383
author: Johannes Rahe
author_email: j.rahe@shopware.com 
author_github: J-Rahe
---
# Administration
*  Added the `verifyUserToken()` method to `src/Administration/Resources/app/administration/src/core/service/login.service.js`
    to have it in one place (was copied into multiple components)
* Deprecated `verifyUserToken()` in 
    `src/Administration/Resources/app/administration/src/app/component/utils/sw-verify-user-modal/index.js`,
    `src/Administration/Resources/app/administration/src/module/sw-profile/page/sw-profile-index/index.js`,
    `src/Administration/Resources/app/administration/src/module/sw-users-permissions/components/sw-users-permissions-user-listing/index.js`
___
# Upgrade Information
## verifyUserToken() method
The verifyUserToken method was available nearly identical in multiple locations.
It has now been integrated into the loginService.js. In case you need to verify a User you can get an Access token
by calling loginService.verifyUserToken(userPassword) and provide the current user's password, the username will be automatically 
fetched from the session.
