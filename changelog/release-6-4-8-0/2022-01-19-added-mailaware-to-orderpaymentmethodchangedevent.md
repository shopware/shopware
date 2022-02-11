---
title: Added MailAware to OrderPaymentMethodChangedEvent
issue: NEXT-19674
author: PuetzD
author_github: PuetzD
---
# Core
* Added `Shopware\Core\Framework\Event\MailAware` to `Shopware\Core\Checkout\Order\Event\OrderPaymentMethodChangedEvent` to enable the FlowBuilder to send emails triggered by the OrderPaymentMethodChangedEvent
