---
title: Use subqueries for joins in OrderAdminSearchIndexer
author: Marcus Müller
author_email: 25648755+M-arcus@users.noreply.github.com
author_github: @M-arcus
---
# Core
* Changed joins for `order_customer`, `order_address`, `order_delivery`, `document` to sub-queries in `OrderAdminSearchIndexer` to improve performance.
