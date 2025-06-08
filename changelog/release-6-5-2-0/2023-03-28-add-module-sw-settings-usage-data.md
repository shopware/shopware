---
title: Add module sw-settings-usage-data
issue: NEXT-25553
author: Frederik Schmitt
author_email: f.schmitt@shopware.com
author_github: fschmtt
---
# Core
* Added system config `core.metrics.shareUsageData` to indicate whether usage data should be shared with Shopware
* Added `Shopware\Core\System\Metrics\Api\MetricController`
* Added `Shopware\Core\System\Metrics\Approval\ApprovalDetector` to check whether usage data sharing approval request is needed
___
# API
* Added a GET route `/api/metrics/needs-approval` to check whether usage data sharing approval request is needed and still to be asked
___
# Administration
* Added module `src/module/sw-settings-usage-data`
* Added component `src/module/sw-settings-usage-data/page/sw-settings-usage-data`
* Added component `src/app/component/sw-usage-data/sw-settings-usage-data-intro`
* Added component `src/app/component/sw-usage-data/sw-settings-usage-data-modal`
