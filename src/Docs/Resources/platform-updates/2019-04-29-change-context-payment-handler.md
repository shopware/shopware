[titleEn]: <>(Changed context injected to payment handler)

From now on the `\Shopware\Core\System\SalesChannel\SalesChannelContext` is injected into the methods of
 `\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\SynchronousPaymentHandlerInterface` and
`\Shopware\Core\Checkout\Payment\Cart\PaymentHandler\AsynchronousPaymentHandlerInterface`. 
This should give you more information about the current checkout and saleschannel context,
but it breaks the current interfaces. Please adjust your payment handler accordingly.
Please be also aware that the SalesChannelContext __may__ contain certain information. Some of its properties are nullable,
so make sure they are set, before you use them. 
