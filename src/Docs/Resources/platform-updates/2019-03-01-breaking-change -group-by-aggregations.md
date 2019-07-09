[titleEn]: <>(Breaking change -&nbsp;GroupBy-Aggregations)
[__RAW__]: <>(__RAW__)

<p>It is now possible to group aggregations by the value of given fields. Just like GROUP BY in SQL works.</p>

<p>Every aggregation now takes a list of groupByFields as the last parameters.</p>

<p>The following Aggregation will be grouped by the category name and the manufacturer name of the product.</p>

<pre>
new AvgAggregation(&#39;product.price.gross&#39;, &#39;price_agg&#39;, &#39;product.categories.name&#39;, &#39;product.manufacturer.name&#39;)
</pre>

<p><strong>Aggregation Result</strong><br />
As aggregations can now return more than one result the `getResult()`-method returns now an array in the following form for non grouped aggregations.</p>

<pre>
[
&nbsp;&nbsp; &nbsp;[
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;key&#39; =&gt; null,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;avg&#39; =&gt; 13.33
&nbsp;&nbsp; &nbsp;]
]</pre>

<p>For grouped Aggregations it will return an array in this form:</p>

<pre>
[
&nbsp;&nbsp; &nbsp;[
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;key&#39; =&gt; [
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;product.categories.name&#39; =&gt; &#39;category1&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;product.manufacturer.name&#39; =&gt; &#39;manufacturer1&#39;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;],
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;avg&#39; =&gt; 13.33
&nbsp;&nbsp; &nbsp;],
&nbsp;&nbsp; &nbsp;[
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;key&#39; =&gt; [
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;product.categories.name&#39; =&gt; &#39;category2&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;product.manufacturer.name&#39; =&gt; &#39;manufacturer2&#39;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;],
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;avg&#39; =&gt; 33
&nbsp;&nbsp; &nbsp;]
&nbsp;&nbsp; &nbsp;
]</pre>

<p>The AggregationResult has a helper method `getResultByKey()` which returns the specific result for a given key:</p>

<pre>
$aggregationResult-&gt;getResultByKey([
&nbsp;&nbsp; &nbsp;&#39;product.categories.name&#39; =&gt; &#39;category1&#39;,
&nbsp;&nbsp; &nbsp;&#39;product.manufacturer.name&#39; =&gt; &#39;manufacturer1&#39;
]);</pre>

<p>will return:</p>

<pre>
[
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;key&#39; =&gt; [
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;product.categories.name&#39; =&gt; &#39;category1&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;product.manufacturer.name&#39; =&gt; &#39;manufacturer1&#39;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;],
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&#39;avg&#39; =&gt; 13.33
&nbsp;&nbsp; &nbsp;],</pre>

<p>The Aggregation result for the specific aggregations are deleted and just the generic AbstractAggregationResult exists.</p>

<p><strong>FIXING EXISTING AGGREGATIONS</strong><br />
As existing aggregations can&#39;t use groupBy you can simply use the first array index of the returned result:</p>

<pre>
/** @var AvgAggregationResult **/
$aggregationResult-&gt;getAverage();</pre>

<p>will become:</p>

<pre>
$aggregationResult-&gt;getResult()[0][&#39;avg&#39;];</pre>

<p>In the administration you also have to add the zero array index.</p>

<pre>
response.aggregations.orderAmount.sum;</pre>

<p>will become:</p>

<pre>
response.aggregations.orderAmount[0].sum;
</pre>
