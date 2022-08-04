---
title: Fix sales channel variable in produxt-export preview
issue: NEXT-21918
author: Patrick Stahl
author_email: p.stahl@shopware.com
author_github: PaddyS
---
# Core
* Changed the assignment of the `SalesChannel` variable in `ProductExportController::generateExportPreview` to use the right sales channel
