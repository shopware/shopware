---
title: Improve core checkout domain performance
author: Benjamin Wittwer
author_email: benjamin.wittwer@a-k-f.de
author_github: akf-bw
---
# Core
* Changed `Shopware\Core\Checkout\Cart\PriceActionController` to use `PartialEntity`
* Changed `Shopware\Core\Checkout\Cart\Order\OrderConverter` to use `PartialEntity`
* Changed `Shopware\Core\Checkout\Cart\Order\RecalculationService` to use `searchIds`
* Changed `Shopware\Core\Checkout\Customer\DeleteUnusedGuestCustomerService` to use `searchIds`
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\ChangePaymentMethodRoute` to use `searchIds`
* Changed `Shopware\Core\Checkout\Customer\SalesChannel\RegisterRoute` to use `PartialEntity`
