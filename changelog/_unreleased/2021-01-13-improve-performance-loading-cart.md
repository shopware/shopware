---
title:              Improve performance on loading cart by loading rules on every request made
issue:              NEXT-13250
author:             Christoph Pötz
author_email:       christoph.poetz@acris.at
author_github:      @acris-cp
---
# Core
*  Removed checking for description and cover in function `isComplete` and reduce loading products again which are already loaded in cart in `src/core/Content/Product/Cart/ProductCartProcessor.php`
*  Added flag modified to already existing lineItems in cart `src/core/Checkout/Cart/SalesChannel/CartItemAddRoute.php`
