[titleEn]: <>(Refactored viewData (Breaking change))
[__RAW__]: <>(__RAW__)

<p>We have completely removed the<strong> Entity::viewData </strong>property.</p>

<p><strong>Why was it removed?</strong></p>

<ul>
	<li>ViewData was always available in the json response under meta. However, this led to deduplication becoming obsolete.</li>
	<li>It had a massive impact on the hydrator performance</li>
</ul>

<p><br />
<strong>What was viewData needed for?</strong></p>

<ul>
	<li>Generally this was needed for translatable fields. The name from the language inheritance was available under viewData.name.</li>
	<li>Furthermore, this was also used for the parent-inheritance (currently used only for products). If a varaint has no own assigned manufacturer, the manufacturer of the parent should be available. Under viewData.manufacturer therefore the manufacturer of the father product was available</li>
</ul>

<p><strong>How do I get this information now?</strong></p>

<ul>
	<li>Translate fields are now available under the translated. The values listed there were determined using the language inheritance.</li>
	<li>The context object contains a switch &quot;considerInheritance&quot;. This can be sent via api as header (sw-inheritance) to consider the inheritance in search and read requests.</li>
</ul>

<p>This value is initialized for the following routes as follows</p>

<p><strong>/api </strong>Default <strong>false</strong><br />
<strong>/sales-channel-api </strong>Default <strong>true</strong><br />
<strong>twig-frontend</strong> Default <strong>true</strong></p>
