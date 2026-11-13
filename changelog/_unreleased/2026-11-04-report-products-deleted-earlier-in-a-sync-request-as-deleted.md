---
title: Report products deleted earlier in a sync request as deleted
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter` so that a sync request that deletes a product and then its prices, media, cross-sellings, manufacturer or another entity whose deletion would update that product no longer reports the product as updated, as in 6.7. The product now appears in `deleted` of the sync response and triggers `product.deleted` instead of `product.written`.
