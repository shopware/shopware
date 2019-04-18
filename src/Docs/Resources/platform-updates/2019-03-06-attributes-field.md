[titleEn]: <>(AttributesField)
[__RAW__]: <>(__RAW__)

<p>We added an easy way to add custom attributes to entities. The AttributesField is like the JsonField only dynamically typed. To save attributes to entities you first have to define the attribute:</p>

<pre>
$attributesRepository-&gt;create([[
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;id&#39; =&gt; &#39;&lt;uuid&gt;&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;name&#39; =&gt; &#39;sw_test_float&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;type&#39; =&gt; AttributeType::Float,
&nbsp;&nbsp; &nbsp;]],
&nbsp;&nbsp; &nbsp;$context
);
</pre>

<p>Then you can save it like a normal json field</p>

<pre>
$entityRepository-&gt;update([[
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;id&#39; =&gt; &#39;&lt;entity id&#39;&gt;&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;attributes&#39; =&gt; [
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;sw_test_float&#39; =&gt; 10.1
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;]
&nbsp;&nbsp; &nbsp;]],
&nbsp;&nbsp; &nbsp;$context
);</pre>

<p>Unlike the JsonField, the AttributesField patchs the data instead of replacing it completely. So you dont need to send the whole object to update one property.</p>
