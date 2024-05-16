---
title: Add admin vite poc
issue: NEXT-35975
flag: ADMIN_VITE
author: Sebastian Seggewiss
author_email: s.seggewiss@shopware.com
author_github: @seggewiss
---
# Core
* Changed `Core/Framework/Feature` to only activate major flags with `FEATURE_ALL=major`
___
# Administration
* Added Vite as a PoC. Toggle `ADMIN_VITE=1` and use `composer run watch:admin` to use the **experimental** Vite build.
