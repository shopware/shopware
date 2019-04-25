[titleEn]: <>(PaymentTransactionStruct changed)
[__RAW__]: <>(__RAW__)

<p>We changed the contents of the <strong>\Shopware\Core\Checkout\Payment\Cart\PaymentTransactionStruct</strong></p>

<p>Now it contains the whole <strong>\Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionEntity </strong>object, from which you should get all necessary information about the order, customer and transaction. The second property is the returnUrl.</p>

<p>Due to this change, you need to adjust your PaymentHandler. Have a look at our PayPal plugins which changes are necessary: <a href="https://github.com/shopwareLabs/SwagPayPal/commit/af5532361be7d0d54c055896a340ee7574df2d66">https://github.com/shopwareLabs/SwagPayPal/commit/af5532361be7d0d54c055896a340ee7574df2d66</a></p>
