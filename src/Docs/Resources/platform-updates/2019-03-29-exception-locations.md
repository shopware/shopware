[titleEn]: <>(Exception Locations)
[__RAW__]: <>(__RAW__)

<p>Just removed the last of the global exceptions. From now on, please move custom exceptions into the <strong>module that throws it.</strong></p>

<p>For example:</p>

<ul>
	<li>Shopware\Core\Checkout\Cart\Exception</li>
	<li>Shopware\CoreFrmaework\DataAbstractionLayer\Exception</li>
</ul>

<p>Not</p>

<ul>
	<li>Shopware\Core\Checkout\Exception</li>
	<li>Shopware\Core\Content\Exception</li>
</ul>

<p><br />
In Perspective all Exception will move to a <strong>\Exception</strong> Folder, so pleas do no longer put them inline with the executing classes</p>

<p><em>FYI: There is a test that checks this :zwinkern:</em></p>
