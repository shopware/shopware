---
title: Trigger admin worker message consumption on import export start
issue: NEXT-19412
author: d.neustadt
author_email: d.neustadt@shopware.com
author_github: dneustadt
---
# Administration
* Added function `cancelConsumeMessages` in `admin-worker.worker.js`
* Added function `addOnProgressStartedListener` in `importExport.service.js`
