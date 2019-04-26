[titleEn]: <>(Default constants removed)
[__RAW__]: <>(__RAW__)

<p>We removed a ton of constants from our super global <strong>Defaults-object.</strong></p>

<p>Please rebase your branch and run <strong>phpstan</strong> to check that you don&#39;t use any of the removed constants.</p>

<p>If you use some <strong>stateMachineConstants</strong> -&gt;<br />
They are moved to its own classes:</p>

<p><strong>OrderStates</strong></p>

<ul>
	<li>Shopware\Core\Checkout\Order\Aggregate\OrderDelivery\OrderDeliveryStates</li>
	<li>Shopware\Core\Checkout\Order\Aggregate\OrderTransaction\OrderTransactionStates</li>
	<li>Shopware\Core\Checkout\Order\OrderStates</li>
</ul>

<p>&nbsp;If you used some other constants, you have to replace them by a query to get the correct <strong>Id</strong></p>
