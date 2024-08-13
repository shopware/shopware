---
title: Adding beforeUpdateQuantity property to BeforeLineItemQuantityChangedEvent after line item quantity modification
issue: 
author: Carlo Cecco
author_email: 6672778+luminalpark@users.noreply.github.com
author_github: @luminalpark
---
# Core
* Added '$beforeUpdateQuantity' parameter to the constructor of `BeforeLineItemQuantityChangedEvent` to allow plugins to understand if quantity is being increased or decreased.
