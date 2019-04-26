[titleEn]: <>(Major issues fixed in admin data handling)
[__RAW__]: <>(__RAW__)

<p>We fixed two issues in the current data handling of the administration.</p>

<p><strong>Hydration of associated entities as instances of EntityProxy</strong><br />
Because of an issue with the method of deep copying objects, the hydrated asssociations of an entity were simple objects. We now fixed this behaviour, so the N:M relations are also instances of EntityProxy. The hydrated associations in the draft of the entity keep the reference to the entity in the association store.</p>

<p><strong>BEFORE</strong></p>

<pre>
// EntityProxy
product = {
&nbsp;&nbsp; &nbsp;categories: [
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;Object {}
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;Object {}
&nbsp;&nbsp; &nbsp;]
}</pre>

<p><strong>AFTER</strong></p>

<pre>
// EntityProxy
product = {
&nbsp;&nbsp; &nbsp;categories: [
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;Proxy {}
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;Proxy {}
&nbsp;&nbsp; &nbsp;]
}</pre>
