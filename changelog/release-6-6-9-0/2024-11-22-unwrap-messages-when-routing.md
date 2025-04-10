---
title: Unwrap messages when routing
issue: NEXT-39749
---
# Core
* Changed `\Shopware\Core\Framework\Adapter\Messenger\MessageBus::getTransports` to unwrap Envelope message so that they are correctly routed
