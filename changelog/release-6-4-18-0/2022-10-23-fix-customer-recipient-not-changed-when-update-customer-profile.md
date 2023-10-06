---
title: Fix customer recipient not changed when update customer profile.
issue: NEXT-22257
---
# Core
* Added `updateCustomersRecipient` in `Shopware\Core\Content\Newsletter\DataAbstractionLayer\Indexing\CustomerNewsletterSalesChannelsUpdater` to update when customer profile changed.
* Changed public function `update` and `handle` in `Shopware\Core\Checkout\Customer\DataAbstractionLayer\CustomerIndexer` to check property change and handle it.
