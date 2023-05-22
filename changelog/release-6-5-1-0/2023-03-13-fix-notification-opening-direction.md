---
title: Fix notification opening direction
issue: NEXT-16914
author: Jannis Leifeld
author_email: j.leifeld@shopware.com
author_github: Jannis Leifeld
---
# Administration
* Changed the opening algorithm of the popover directive so that it opens to the side with the most space available instead of rotating through all sides and using the last one if no side has enough space
