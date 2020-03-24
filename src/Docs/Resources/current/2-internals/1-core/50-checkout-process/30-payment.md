[titleEn]: <>(Payment)
[hash]: <>(article:checkout_payment)

Shopware 6's payment system is an integral Part of the Checkout process. A payment is applied to a transaction of an order. As with any order change this is done through the state machine. At its core the payment system is composed from *payment handlers*, these extend Shopware to support multiple different payment types. A list of all payment handlers is stored [in the database](./../10-erd/erd-shopware-core-checkout-payment.md). 

## Payment handler

Payment handlers get executed after an order was placed. Per default this happens during the checkout. 

![async payment](./dist/payment-async.png) 

As illustrated in the diagram above, the payment handler is invoked to redirect to the foreign system handling the payment. This foreign system then notifies the Platform and the successful payment is stored. This is achieved by a handler implementing [`\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface`](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Payment/Cart/PaymentHandler/AsynchronousPaymentHandlerInterface.php)

This interface is composed out of two methods:

* `pay`: will be called after an order has been placed. 
   You receive a `Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct` which contains the transactionId, order details, the amount of the transaction, return URL, payment method information and language information. Please be aware, Shopware supports transactions and you must use the amount provided and not the total order amount. The pay method can return a `RedirectResponse` to redirect the customer to an external payment gateway. 
   Note: The `PaymentTransactionStruct` contains a return URL. Pass this URL to the external payment gateway to ensure that the user will be redirected to this URL.
* `finalize`: will only be called if you returned a `RedirectResponse` in your `pay` method and the customer has been redirected from the payment gateway back to Shopware. You might check here if the payment was successful or not and update the order transaction state accordingly.

## Transaction handler

The order module provides a useful interface to simplify state changes on a transaction. [`\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStateHandler`](https://github.com/shopware/platform/blob/master/src/Core/Checkout/Order/Aggregate/OrderTransaction/OrderTransactionStateHandler.php) contains the state change methods as a pragmatical interface.
