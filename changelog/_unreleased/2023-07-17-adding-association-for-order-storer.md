---
title: Fix product cover image is missing in email templates
issue: NEXT-28693
---
# Core
* Added `lineItems.cover` association in `src/Core/Content/Flow/Dispatching/Storer/OrderStorer.php` to show the product cover in email templates
