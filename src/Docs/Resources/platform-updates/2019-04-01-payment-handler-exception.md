[titleEn]: <>(Payment handler exception)
[__RAW__]: <>(__RAW__)

<p>Payment handler are now able to throw special exceptions if certain error cases occur.</p>

<ul>
	<li><strong>Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface::pay </strong>should throw the<strong> SyncPaymentProcessException</strong> if something goes wrong.</li>
	<li>Same for the <strong>Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface::pay</strong> Throw an <strong>AsyncPaymentProcessException</strong> e.g. if a call to an external API fails</li>
	<li>The finalize method of the <strong>AsynchronousPaymentHandlerInterface</strong> could also throw an <strong>AsyncPaymentFinalizeException</strong>. Additionally it could throw a <strong>CustomerCanceledAsyncPaymentException</strong> if the customer canceled the process on the payment provider page.</li>
</ul>

<p>In every case, Shopware catches these exceptions and set the transaction state to <strong>canceled</strong> before the exceptions are thrown again. So a caller of the Shopware pay API route will get an exception message, if something goes wrong during the payment process and could react accordingly.</p>

<p>Soonish it will be possible to transform the order into a cart again and let the customer update the payment method or something like that. Afterwards the order will be updatet und persisted again.</p>

<p>Have a look at the <a href="https://github.com/shopware/platform/blob/master/src/Docs/Resources/current/4-how-to/010-payment-plugin.md">Docs</a> or at our <a href="https://github.com/shopwareLabs/SwagPayPal/blob/master/Core/Checkout/Payment/Cart/PaymentHandler/PayPalPayment.php">PayPal Plugin</a> for examples</p>
