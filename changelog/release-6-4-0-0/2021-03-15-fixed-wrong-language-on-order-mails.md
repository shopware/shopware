---
title: Fixed wrong language on order mails
issue: NEXT-13909
---
# Core
*  Changed methods `onOrderDeliveryStateChange`, `onOrderTransactionStateChange` and `onOrderStateChange`, `Shopware\Core\Checkout\Order\Listener\OrderStateChangeEventListener` to resolve the correct context for the current order 
