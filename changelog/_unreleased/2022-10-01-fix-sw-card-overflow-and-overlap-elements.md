---
title: Fix sw-card overflow and overlap elements
issue: NEXT-22733
author: Stephan Franck
author_email: stephan@vierpunkt.de
author_github: stephan4p
---
# Administration
* Change dropshadow from CSS `filter` to `box-shadow` to resolve an stacking context problem if an elements overflow the card and follow by a card
