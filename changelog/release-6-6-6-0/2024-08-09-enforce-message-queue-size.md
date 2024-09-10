---
title: Enforce message queue size
issue: NEXT-37100
---

# Core

* Added `Shopware\Core\Framework\MessageQueue\Subscriber\MessageQueueSizeRestrictListener` to limit the message queue message size to 256KB. It only creates a log entry if a message is bigger than 256KB.
* Deprecated MessageQueue message size. Messages bigger than 256KB will throw an exception with Shopware 6.7

___
# Next Major Version Changes

## Message queue size limit

Any message queue message bigger than 256KB will be now rejected by default.
To reduce the size of your messages you should only store the ID of an entity in the message and fetch it later in the message handler.
This can be disabled again with:

```yaml
shopware:
    messenger:
        enforce_message_size: false

```
