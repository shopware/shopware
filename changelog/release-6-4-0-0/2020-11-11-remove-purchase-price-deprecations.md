---
title: Remove purchase_price deprecations
issue: NEXT-10734

---
# Core
* Removed field `purchasePrice` in `Core\Content\Product\ProductDefinition`.
* Removed property `purchasePrice` in `Core\Content\Product\ProductEntity`.
* Removed `Core\Content\Product\DataAbstractionLayer\ProductPurchasePriceDeprecationUpdater`.
* Removed `purchasePrice` from LineItem payload in `\Shopware\Core\Content\Product\Cart\ProductCartProcessor::collect`
