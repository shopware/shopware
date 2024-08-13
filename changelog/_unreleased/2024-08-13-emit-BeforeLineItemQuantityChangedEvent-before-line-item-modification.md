---
title: Emit BeforeLineItemQuantityChangedEvent before line item modification
issue: 
author: Carlo Cecco
author_email: 6672778+luminalpark@users.noreply.github.com
author_github: @luminalpark
---
# Core
* Moved the `BeforeLineItemQuantityChangedEvent` in `LineItemFactoryRegistry\updateLineItem` method to be emitted before the line item is modified. This allows plugins to process the information effectively before the quantity is modified.
* Added '$newQuantity' parameter to the constructor of `BeforeLineItemQuantityChangedEvent` to allow plugins to understand if quantity is being increased or decreased.
