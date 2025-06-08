---
title: Deleting a customer causes errors when changing the order state of his order
issue: NEXT-29191
---
# Core
* Changed method `store()` from `Shopware\Core\Content\Flow\Dispatching\Storer\CustomerStorer` to continue process if the exception `CustomerDeletedException` was thrown
