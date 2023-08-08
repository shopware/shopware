---
title: Fix slow recompile time in webpack dev server
issue: NEXT-29409
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Changed CleanWebpackPlugin so that it only runs in production build. This fixes the slow recompile time in webpack dev server.
