---
title: Fix $super call stack exception
issue: NEXT-36774
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Administration
* Changed `async-component.factory.ts` to fix a call stack exception calling `$super` from an extended override.
