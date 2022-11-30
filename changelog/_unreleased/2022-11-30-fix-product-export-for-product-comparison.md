---
title: Fix Product Export for Product Comparison
issue: NEXT-24393
author: Jeremy Bastian Kemmler
author_email: jbk@alphanauten.de
author_github: jbk@alphanauten.de
---
# Core
* Changed File "src/Core/Content/ProductExport/ScheduledTask/ProductExportGenerateTaskHandler.php", on Line 128, changed Equals Filter to Equals Any and Added Product Comparison Sales Channel Id 
* Changed File "src/Core/Content/ProductExport/Service/ProductExportGenerator.php", added a new way to count the total and removed the old iteration call for the total count. This will give an accurate total based on the filtering of $isIncludeVariants.
* Changed File "src/Core/Content/ProductExport/Service/ProductExportGenerator.php", on Line 146, allows Sales Channels of type Product Comparison
