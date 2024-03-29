---
title: Fix async-component factory edge case
issue: NEXT-33431
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Added a condition in the super call to skip the current call when not method is available
