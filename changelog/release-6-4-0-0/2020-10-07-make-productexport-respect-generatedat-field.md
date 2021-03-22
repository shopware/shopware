---
title:  Make ProductExport respect generatedAt and interval fields
issue: NEXT-13429
author: Hendrik Söbbing
author_email: hendrik@soebbing.de
author_github: @soebbing
---
# Core
* Changed `\Shopware\Core\Content\ProductExport\ScheduledTask\ProductExportGenerateTaskHandler` to respect the product exports
defined `generatedAt` and `interval` fields
