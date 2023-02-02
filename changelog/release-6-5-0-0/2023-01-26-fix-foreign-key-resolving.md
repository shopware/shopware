---
title: Fix foreign key resolving
issue: NEXT-22262
author: Oliver Skroblin
author_email: o.skroblin@shopware.com
---

# Core
* Added new `NoConstraint()` flag for definition fields to disable fk constraint checks for this field
* Added `NoConstraint()` flag to the following fields
  * `customer.default_billing_address_id`
  * `customer.default_shipping_address_id`
  * `order.billing_address_id`
  * `product.product_media_id`
* Changed association `media.avatarUser` from one-to-one to one-to-many
  * Renamed `media.avatarUser` to `media.avatarUsers`
  * Renamed `MediaEntity::$avatarUser` to `MediaEntity::$avatarUsers`
  * Changed `MediaEntity::$avatarUser` type from `UserEntity|null` to `UserCollection|null`
* Changed behavior of `WriteCommandQueue::getCommandsInOrder` so that each command is resolved individually in the write order to avoid possible circular dependencies much better
* Fixed `VersionManager` so that now all versioned entries are completely deleted


