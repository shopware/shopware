---
title: Consistently generate distinguishable names
issue: NEXT-15331
flag: NEXT-15170
---
# Core
* Added `Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameGenerator` to generate distinguishable names
* Changed `Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentDistinguishableNameSubscriber` to only adding distinguishable names as fallback
* Added `Shopware\Core\Checkout\Payment\DataAbstractionLayer\PaymentMethodIndexer`
* Added `Shopware\Core\Checkout\Payment\Event\PaymentMethodIndexerEvent`
* Changed `Shopware\Core\Migration\V6_4\Migration1620733405DistinguishablePaymentMethodName` to trigger new `PaymentMethodIndexer`
