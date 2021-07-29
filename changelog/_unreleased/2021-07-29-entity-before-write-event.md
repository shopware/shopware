---
title: Added new event to validate entity payload before write
issue: 1983
author: Philipp Georg
author_email: info@moorleiche.com
author_github: moorl
---
# Core
* new class `\Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWritePayloadEvent`
* `\Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter` added `EventDispatcher` service
* `\Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter::write` added `EntityWritePayloadEvent`
