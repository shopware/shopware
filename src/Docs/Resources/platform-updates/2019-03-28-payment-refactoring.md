[titleEn]: <>(Payment refactoring)
[__RAW__]: <>(__RAW__)

<p>We deleted the entity properties:</p>

<ul>
	<li>template</li>
	<li>class</li>
	<li>percentageSurcharge</li>
	<li>absoluteSurcharge</li>
	<li>surchargeText</li>
</ul>

<p>and renamed the <strong>technicalName</strong> to <strong>handlerIdentifier</strong>, which isn&acute;t unique anymore.</p>

<p>The <strong>handlerIdentifier</strong> is only internal and can not be written by the API. It contains the class of the identifier. If a plugin is created via the admin, the <strong>Shopware\Core\Checkout\Payment\Cart\PaymentHandler\DefaultPayment</strong> handler will be choosed.</p>

<p>Also we divided the <strong>PaymentHandlerInterface</strong> into two payment handler interfaces:</p>

<ul>
	<li>AsynchronousPaymentHandlerInterface</li>
	<li>SynchronousPaymentHandlerInterface</li>
</ul>

<p>and also added the two new structs:</p>

<ul>
	<li>AsyncPaymentTransactionStruct</li>
	<li>SyncPaymentTransactionStruct</li>
</ul>

<p>The <strong>AsynchronousPaymentHandlerInterface</strong> has a <strong>finalize</strong> Method and the <strong>pay</strong> Method returns a <strong>RedirectResponse</strong>. In the <strong>SynchronousPaymentHandlerInterface</strong> we only have the <strong>pay</strong> Methods wich has no return.</p>

<p>Another change is a decoration of the payment repository which prevents to delete a plugin payment via API. Payments without a plugin id can be deleted via API. For plugin payments deletions, the plugin itself has to use the new method <strong>internalDelete</strong>, which uses the normal undecorated delete method without restrictions.</p>
