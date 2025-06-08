---
title: Fix missing error messages from sync-api
issue: NEXT-13477
author: Tobias Berge
author_email: t.berge@shopware.com 
author_github: @tobiasberge
---
# Administration
* Changed function `globalErrorHandlingInterceptor` in `Resources/app/administration/src/core/factory/http.factory.js`
    * Added new function `handleErrorStates` and refactored status code specific logic into this new function and added a new condition for status code 400 errors from sync-api
    * Changed `globalErrorHandlingInterceptor` and moved individual try-catch blocks into one global try-catch inside `handleErrorStates`
