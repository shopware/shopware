---
title: Avoid to set active billing address with null 
issue: NEXT-34384
author: Florian Keller
author_email: f.keller@shopware.com
---
# Core
* Changed `src/Core/System/SalesChannel/Context/SalesChannelContextFactory.php` to avoid calling `Shopware\Core\Checkout\Customer\CustomerEntity::setActiveBillingAddress()` with null. 
