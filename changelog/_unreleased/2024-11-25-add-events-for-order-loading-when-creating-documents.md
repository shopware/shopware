---
title: Add events for order loading when creating documents
issue: NEXT-0000
author: Max
author_email: max@swk-web.com
author_github: @aragon999
---
# Core
* Added events `Shopware\Core\Checkout\Document\Event\{CreditNoteOrderCriteriaEvent,DeliveryNoteOrderCriteriaEvent,InvoiceOrderCriteriaEvent,StornoOrderCriteriaEvent}` to modify the criteria when loading the order for the respective documents
