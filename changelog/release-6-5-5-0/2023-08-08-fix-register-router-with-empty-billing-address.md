---
title: Fix register router with empty billing address
issue: NEXT-29633
---
# Core
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute::register` to set default `billingAddress`.
