---
title: Fix auto logout in Safari
issue: NEXT-33394
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `login.service.ts` to auto logout in Safari when the user is inactive for 30 minutes
