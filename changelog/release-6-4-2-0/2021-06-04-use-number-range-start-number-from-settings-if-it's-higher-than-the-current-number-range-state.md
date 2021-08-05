---
title: Use number range start number from settings if it's higher than the current number range state
issue: NEXT-11038
author: d.neustadt
author_email: d.neustadt@shopware.com 
author_github: dneustadt
---
# Core
* Changed the number range increment to be based either on the number range start from configuration or on the current number range state, whichever is highest.
___
# Administration
* Added an info notification to number range module to communicate that changes to the start number will only take effect is the new start number is higher than the current number range state.
