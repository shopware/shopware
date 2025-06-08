---
title: Fix skipped import export notification
issue: NEXT-17069
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Administration
* Changed import export service and components to add activity log immediately after starting the process to avoid success and warning notifications not being triggered
