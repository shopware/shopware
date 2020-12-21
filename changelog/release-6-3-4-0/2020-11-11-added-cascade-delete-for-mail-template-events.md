---
title: added cascade delete for mail template events
issue: NEXT-12040
author: OliverSkroblin
author_email: o.skroblin@shopware.com 
author_github: OliverSkroblin
---
# Core
* Added `EventAction/EventActionSubscriber.php`, to delete all assigned event action records, when a mail_template record is deleted.
___
# Administration
* Changed `sw-event-action/page/sw-event-action-list/index.js`, to validate if the assigned mail templates exists
* Deprecated `sw-event-action/page/sw-event-action-list/index.js::renderMailTemplate`
