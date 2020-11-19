---
title: Fix migration for cover id constraint in order line items
issue: NEXT-12033
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Changed `src/Core/Migration/Migration1600676671OrderLineItemCoverMedia.php` to reset invalid cover ids.
