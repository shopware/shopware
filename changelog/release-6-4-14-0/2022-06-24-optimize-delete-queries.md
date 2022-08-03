---
title: Optimize Delete queries on cart and version tables
issue: next-22163
author: Micha Hobert
author_email: info@the-cake-shop.de
author_github: Isengo1989
---
# Core
* Adding a do-while loop and limit to the `DELETE` query in `CleanupCartTaskHandler` and `CleanupVersionTaskHandler` to 1000 entries. This is necessary when a scheduled task failed / was inactive for some time
