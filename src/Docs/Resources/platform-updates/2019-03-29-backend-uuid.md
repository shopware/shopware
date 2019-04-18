[titleEn]: <>(Backend UUID)
[__RAW__]: <>(__RAW__)

<p>The Uuid class was moved from <strong>FrameworkStruct\Uuid </strong>to<strong> Framework\Uuid\Uuid </strong>please adjust your branches.</p>

<p><strong>Changes:</strong></p>

<ul>
	<li>The new class does no longer support a<strong> ::uuid4() </strong>please use <strong>::randomHex() </strong>or <strong>::randomBytes() </strong>instead</li>
	<li>The string format (with the dashes like <strong>123456-1234-1234-1234-12345679812</strong>) is no longer supported, methods are removed</li>
	<li>The Exceptions moved to <strong>Framework\Uuid\Exception&nbsp;</strong></li>
</ul>

<p><br />
Backwards Compatibility:</p>

<p>You can still use the old class, but it is deprecated and will be removed next week.</p>
