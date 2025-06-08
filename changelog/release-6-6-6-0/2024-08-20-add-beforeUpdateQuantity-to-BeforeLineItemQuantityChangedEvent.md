---
title: Adding beforeUpdateQuantity property to BeforeLineItemQuantityChangedEvent after line item quantity modification
issue: NEXT-37783
author: Carlo Cecco
author_email: 6672778+luminalpark@users.noreply.github.com
author_github: @luminalpark
---
# Core
* Added '$beforeUpdateQuantity' property to `BeforeLineItemQuantityChangedEvent` to allow plugins to understand if quantity is being increased or decreased.
