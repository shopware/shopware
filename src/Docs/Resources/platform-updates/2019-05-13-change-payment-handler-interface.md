[titleEn]: <>(Added RequestDataBag to interface of payment handler)

The `\Shopware\Core\Framework\Validation\DataBag\RequestDataBag` was added to the pay methods of
 `\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface` and
`\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface`. 
With this, you are able to send custom parameter into the payment handling.
