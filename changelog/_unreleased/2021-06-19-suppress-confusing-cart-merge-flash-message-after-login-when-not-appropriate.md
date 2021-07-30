---
title: Suppress confusing cart merge flash message after login when not appropriate
issue: NEXT-16243
author: Axel Guckelsberger
author_email: axel.guckelsberger@guite.de
---
# Core
* Changed trigger logic for `CartMergedEvent`. The `CartMergedEvent` event is now only thrown if a merge of a previous shopping cart has really taken place.
