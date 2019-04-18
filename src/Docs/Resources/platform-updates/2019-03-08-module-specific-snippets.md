[titleEn]: <>(Module specific snippets)
[__RAW__]: <>(__RAW__)

<p>We had the problem that our two big snippets files for the administration caused a bunch of merge conflicts in the past.</p>

<p>Now it&#39;s possible to register module specific snippets, e.g. each module can have their own snippet files.</p>

<pre>
import deDE from &#39;./snippet/de_DE.json&#39;;
import enGB from &#39;./snippet/en_GB.json&#39;;

Module.register(&#39;sw-configuration&#39;, {
&nbsp;&nbsp; &nbsp;// ...

&nbsp; &nbsp; snippets: {
&nbsp; &nbsp; &nbsp; &nbsp; &#39;de-DE&#39;: deDE,
&nbsp; &nbsp; &nbsp; &nbsp; &#39;en-GB&#39;: enGB
&nbsp; &nbsp; },

&nbsp;&nbsp; &nbsp;// ...
});</pre>

<p>The module has a new property called snippetswhich should contain the ISO codes for different languages.</p>

<p>Inside the JSON files you still need the module key in place:</p>

<pre>
{
&nbsp; &nbsp;&quot;sw-product&quot;: { ... }
}</pre>

<p>The usage inside components and component templates haven&#39;t changed.</p>
