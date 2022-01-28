---
title: Add min order quantity error
author: Nils Evers
author_email: evers.nils@gmail.com
author_github: NilsEvers
---
# Core
* Added `\Shopware\Core\Content\Product\Cart\MinOrderQuantityError`
* Refactored `\Shopware\Core\Content\Product\Cart\ProductCartProcessor::validateStock` to add an error to the cart when the quantity is modified to match the products min order quantity.
* Updated test case `\Shopware\Core\Content\Test\Product\Cart\ProductCartProcessorTest::testProcessCartShouldReturnFixedQuantity`  
