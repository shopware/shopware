---
title: Fix admin cookies for subdomains
issue: NEXT-18964
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Added `getStorage` function to `login.service.ts`
* Added post initializer `cookie.init.ts` to clear up old cookies on logout
* Changed `login.service.ts` cookie configuration to be domain strict
