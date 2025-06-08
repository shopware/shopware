---
title: Fix admin worker
issue: NEXT-7947
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Core
# Administration
* Changed admin worker behavior to prevent multiple worker requests and random logouts
* Changed public API of `refresh-token.helper` from class to function to prevent multiple token refreshes at the same time which can cause random logouts
