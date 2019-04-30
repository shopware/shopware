[titleEn]: <>(CustomFields)
[__RAW__]: <>(__RAW__)

<p>We added an easy way to add custom fields to entities. The CustomField is like the JsonField only dynamically typed. To save custom fields to entities you first have to define the custom field:</p>

<pre>
$customFieldsRepository-&gt;create([[
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;id&#39; =&gt; &#39;&lt;uuid&gt;&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;name&#39; =&gt; &#39;sw_test_float&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;type&#39; =&gt; CustomFieldType::Float,
&nbsp;&nbsp; &nbsp;]],
&nbsp;&nbsp; &nbsp;$context
);
</pre>

<p>Then you can save it like a normal json field</p>

<pre>
$entityRepository-&gt;update([[
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;id&#39; =&gt; &#39;&lt;entity id&#39;&gt;&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;customFields&#39; =&gt; [
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;sw_test_float&#39; =&gt; 10.1
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;]
&nbsp;&nbsp; &nbsp;]],
&nbsp;&nbsp; &nbsp;$context
);</pre>

<p>Unlike the JsonField, the CustomField patchs the data instead of replacing it completely. So you dont need to send the whole object to update one property.</p>
