---
title: Add intra-community delivery label to all tax relevant documents
issue: NEXT-36528
author: Marina Egner
author_email: marina.egner@pickware.de
author_github: @magraina
---
# Core
* Added new function `isAllowIntraCommunityDelivery` to `StornoRenderer` and `CreditNoteRenderer` to check if the intra-community delivery label should be displayed on the rendered document.
* Added block for the intra-community delivery label in the `payment_shipping.html.twig` template.
* Added new configuration `isAllowIntraCommunityDelivery` to the `DocumentConfiguration` to enable or disable the intra-community delivery label in the document.
* Added `is_eu` field to the `country` entity to check if the customer is from the EU.
