---
title: Import and export in message queue
issue: NEXT-14808
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Added `ImportExportHandler` and `ImportExportMessage` to handle import/export batches through dispatched messages in queue asynchronously
___
# Administration
* Removed progress bar, download buttons and other elements from `sw-import-export-progress` component that relied on import/exports being handled synchronously
* Added functionality to `sw-import-export-activity` component to periodically update import/export activities in progress until processing is finished, resulting in a corresponding notification
