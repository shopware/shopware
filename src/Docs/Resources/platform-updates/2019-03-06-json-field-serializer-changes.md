[titleEn]: <>(JsonField-Serializer changes)
[__RAW__]: <>(__RAW__)

<p>It&#39;s already possible to define types for the values in the json object by passing an array of Fields into propertyMapping. The values are then validated and encoded by the corresponding FieldSerializer.</p>

<p><strong>We implemented two changes:</strong></p>

<ul>
	<li>the decode method now calls the fields decode method and formats \DateTime as \DateTime::ATOM (Example: BoolField values are now decoded as true/false instead of 0/1)</li>
	<li>the JsonFieldAccessorBuilder now casts according to the matching types on the SQL side. That means it&#39;s now possible to correclty filter and aggregate on many typed fields. The following fields are supported:</li>
	<li>IntField</li>
	<li>FloatField</li>
	<li>BoolField</li>
	<li>DateField</li>
</ul>

<p>All other Fields are handled as strings.</p>

<h3>2019-03-05: Make entityName property private</h3>

<p>In order to avoid naming conflicts wirth entities, that define a entityName field, we decided to mark the entityName property in EntityStore and EntityProxy as private by adding a preceding undersore.</p>

<p>In the most cases this will not affect you directly since you should always know, what entities you&#39;re working on. However in mixed lists it can be usefull to make decisions depending on the type. To do so use the new getEntityName() function provided by proxy and store.</p>

<pre>
//example testing vue.js properties
props: {
&nbsp; &nbsp; myEntity: {
&nbsp; &nbsp; &nbsp; &nbsp; type: Object,
&nbsp; &nbsp; &nbsp; &nbsp; required: true,
&nbsp; &nbsp; &nbsp; &nbsp; validator(value) &nbsp;{
&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; return value.getEntityName() === &#39;some_entity_name&#39;;
&nbsp; &nbsp; &nbsp; &nbsp; }
&nbsp; &nbsp; }
}</pre>
