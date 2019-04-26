[titleEn]: <>(Exception conventions)
[__RAW__]: <>(__RAW__)

<p><strong>Error codes</strong><br />
Exceptions are not translated when they are thrown. There there must be an identifier to translate them in the clients. Every exception in Shopware should implement <strong>ShopwareException</strong> or better extend from <strong>ShopwareHttpException</strong>. The interface now requires an getErrorCode() method, which returns an unique identifier for this exception.</p>

<p>The identifier is built of two components. Domain and error summary separated by an underscore, all uppercase and spaces replaced by underscores. For example: <strong>CHECKOUT__CART_IS_EMPTY</strong></p>

<p><strong>Placeholder</strong><br />
In addition, the placeholders in exceptions have been implemented. The ShopwareHttpException constructor has 2 parameters, a message and an array of parameters to be replace within the message. <strong>Please do not use sprintf() anymore!</strong></p>

<p><strong>Example:</strong></p>

<pre>
parent::__construct(
&nbsp;&nbsp; &nbsp;&#39;The type &quot;{{ type }}&quot; is not supported.&#39;,&nbsp;
&nbsp;&nbsp; &nbsp;[&#39;type&#39; =&gt; &#39;foo&#39;]
);</pre>
