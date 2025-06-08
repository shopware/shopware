---
title: Authorized transaction state allows payment method change
issue: NEXT-16229
author: Lennart Tinkloh
author_email: l.tinkloh@shopware.com 
author_github: @lernhart
---
# Core
*  Added `Shopware\Core\Checkout\Order\SalesChannel\OrderService::ALLOWED_TRANSACTION_STATES` which contains payment transaction states, who allow a change of a payment method on an existing order.
*  Added `Shopware\Core\Checkout\Cart\Exception\OrderPaymentMethodNotChangeable` exception.
___
# Storefront
*  Added snippet `editOrderPaymentNotChangeable` for an error message on the order edit page.
*  Added snippet `editOrderPaymentNotChangeableWithRefunds` for an error message on the order edit page, with refunds enabled.
*  Changed logic to check, if a payment method is changeable when editing an order in `Shopware\Storefront\Page\Account\Order\AccountEditOrderPageLoader` to include a check, if the payment transaction state allows to do so.
*  Changed logic to throw an `Shopware\Core\Checkout\Cart\Exception\OrderPaymentMethodNotChangeable` in `Shopware\Storefront\Controller\AccountOrderController::updateOrder` when a customer tries to update a payment method of an order with a transaction state, which does not allow it.
*  Changed `src/Storefront/Resources/views/storefront/page/account/order-history/order-item.html.twig` to only show "Change payment method" button if the transaction state allows to do so.
