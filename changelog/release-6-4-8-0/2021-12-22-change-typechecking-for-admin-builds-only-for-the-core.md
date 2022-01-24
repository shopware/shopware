---
title: Change typechecking for admin builds only for the core
issue: NEXT-19354
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Changed the ForkTsCheckerWebpackPlugin so that it only checks the core build and no plugins. This improves the build performance drastically.
