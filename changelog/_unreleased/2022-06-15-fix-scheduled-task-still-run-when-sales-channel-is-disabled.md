---
title: Scheduled task still run when sales channel is disabled
issue: NEXT-21650
author: Marcel Hakvoort
author_email: m.hakvoort@shopware.com
author_github: @celha
---

# Core
* Changed the `src/Core/Content/ProductExport/ScheduledTask/ProductExportGenerateTaskHandler.php` so that the scheduled tasks only run for active product export sales channels
