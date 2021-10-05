---
title: Drop messages after max retry of 3 times
issue: NEXT-9499
---
# Core
* Changed `\Shopware\Core\Framework\MessageQueue\DeadMessage\RequeueDeadMessagesService` to drop messages after max of 3 retries
