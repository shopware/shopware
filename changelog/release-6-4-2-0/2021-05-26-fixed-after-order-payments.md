---
title: Fixed after oder payments
issue: NEXT-15235
---
# Storefront
*  Changed `\Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader::getPaymentMethods` to use `$criteria` on `paymentMethodRoute::load` 
