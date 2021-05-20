---
title: Fix decrement of message queue stats by using default transport name for receiver in admin message queue
issue: NEXT-15008
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: d.neustadt
---
# Core
* Changed key of receiver to default transport name in `ConsumeMessagesController` so `ReceivedStamp` will return according transport name allowing for message queue stats decrements
