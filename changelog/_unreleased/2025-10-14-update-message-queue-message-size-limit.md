---
title: Update message queue message size limit to 1 MiB
author: Benjamin Wittwer
author_email: Discord.Benjamin@web.de
author_github: gecolay
---
# Core
* Changed `MESSAGE_SIZE_LIMIT` in `Framework\MessageQueue\Subscriber\MessageQueueSizeRestrictListener` from 256 KiB to 1 MiB to align with AWS SQS limits
