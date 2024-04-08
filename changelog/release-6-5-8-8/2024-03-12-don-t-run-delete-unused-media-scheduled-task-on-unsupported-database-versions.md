---
title: Don't run delete unused media scheduled task on unsupported database versions
issue: NEXT-31710
---
# Core
* Changed `\Shopware\Core\Content\Product\Cleanup\CleanupUnusedDownloadMediaTaskHandler` to not run on unsupported database versions
