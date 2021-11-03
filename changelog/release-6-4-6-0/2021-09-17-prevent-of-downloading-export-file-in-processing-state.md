---
title: Prevent of downloading export file in processing state
issue: NEXT-17070
author: Krispin Lütjann
author_email: k.luetjann@shopware.com 
author_github: King-of-Babylon
---
# Administration
* Added disabled property to button / context menu item to prevent downloading export file in processing state:
  * `Resources/app/administration/src/module/sw-import-export/component/sw-import-export-activity/sw-import-export-activity.html.twig`
  * `Resources/app/administration/src/module/sw-import-export/component/sw-import-export-activity-log-info-modal/sw-import-export-activity-log-info-modal.html.twig`
* Added a new function `openProcessFileDownload` to `Resources/app/administration/src/module/sw-import-export/component/sw-import-export-activity/index.js`
* Deprecated the function `openFileDownload` in `Resources/app/administration/src/module/sw-import-export/component/sw-import-export-activity/index.js`
