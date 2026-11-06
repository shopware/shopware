---
title: Set null references of entities removed by cascade deletes
issue: 18852
---
# Core
* Changed `Shopware\Core\Framework\DataAbstractionLayer\Write\EntityWriter` to also resolve `SetNullOnDelete` associations of entities removed through `CascadeDelete`, as in 6.7. For associations without a database constraint (`SetNullOnDelete(false)`) the reference is set to `null` and a write result is dispatched for the updated entity. For example, deleting a media that is used as a product cover now clears `product.coverId`. Entities deleted in the same operation are not updated, so deleting a product with a cover still reports the product and its variants as deleted.
