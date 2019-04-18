[titleEn]: <>(New tab component)
[__RAW__]: <>(__RAW__)

<p>The new tab component got a redesign. It supports now horizontal and vertical mode. The vertical mode looks and works like the side-navigation component. This is the reason why it was replaced with this component. You can switch between a left and right alignment.</p>

<p>You can use the sw-tabs-item component for each tab item. It accepts a vue route. When no route is provided then it will be used like a normal link which you can use for every case.</p>

<pre>
&lt;sw-tabs isVertical small alignRight&gt;

&nbsp; &nbsp; &lt;sw-tabs-item :to=&quot;{ name: &#39;sw.explore.index&#39; }&quot;&gt;
&nbsp; &nbsp; &nbsp; &nbsp; Explore
&nbsp; &nbsp; &lt;/sw-tabs-item&gt;

&nbsp; &nbsp; &lt;sw-tabs-item href=&quot;https://www.shopware.com&quot;&gt;
&nbsp; &nbsp; &nbsp; &nbsp; My Plugins
&nbsp; &nbsp; &lt;/sw-tabs-item&gt;

&lt;/sw-tabs&gt;
</pre>
