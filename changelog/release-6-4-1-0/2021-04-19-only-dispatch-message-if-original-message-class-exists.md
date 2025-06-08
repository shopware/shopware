---
title: Only dispatch message if original message class exists
issue: NEXT-14260
---
# Core
* Changed method `\Shopware\Core\Framework\MessageQueue\DeadMessage\RequeueDeadMessagesService::requeue` to only dispatch RetryMessage if original message exists
* Changed method `\Shopware\Core\Framework\MessageQueue\Handler\RetryMessageHandler::handle` to only only call handler::handle method if original message exists
