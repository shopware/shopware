---
title: Add new criteria events
issue: none
author:             Jochen Manz
author_email:       jochen.manz@gmx.de
author_github:      @jochenmanz
---
# Core
* Added `ProductDetailCriteriaEvent`, the event will be dispatched with the criteria before the product is loaded in the `ProductDetailRoute`.
* Added `ProductListingPreviewCriteriaEvent`, the event will be dispatched with the criteria before the actual list items are computed when loading products via the `ProductListingLoader`.
