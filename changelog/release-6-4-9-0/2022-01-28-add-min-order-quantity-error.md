---
title: Add min order quantity error
issue: NEXT-19840
author: Nils Evers
author_email: evers.nils@gmail.com
author_github: NilsEvers
---
# Core
* Added class `Shopware\Core\Content\Product\Cart\MinOrderQuantityError`.
* Changed method `Shopware\Core\Content\Product\Cart\ProductCartProcessor::validateStock()` to add an error to the cart when the quantity is modified to match the products min order quantity.
