---
title: add order with sent document rule condition
issue: NEXT-19984
author: Lars Kemper
author_email: l.kemper@shopware.com
author_github: @LarsKemper
---
# Core
* Added `OrderDocumentTypeSentRule` to the rule builder to check if a specific document type has been sent for an order.
___
# Administration
* Changed `condition-type-data-provider.decorator` to include the new rule condition `orderDocumentTypeSent`.
