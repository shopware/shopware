[titleEn]: <>(Number ranges added)
[__RAW__]: <>(__RAW__)

<p>We implemented a configurable number range.</p>

<p>Number ranges are defined unique identifiers for specific entities.</p>

<p>The new NumberRangeValueGenerator is used to generate a unique identifier for a given entity with a given configuration.</p>

<p>The configuration will be provided in the administration where you can provide a pattern for a specific entity in a specific sales channel.</p>

<p>You can reserve a new value for a number range by calling the route /api/v1/number-range/reserve/{entity}/{salesChannelId} with the name of the entity like product or order and, for sales channel dependent number ranges, also the salesChannelId</p>

<p>In-Code reservation of a new value for a number range can be done by using the NumberRangeValueGenerator method getValue(string $definition, Context $context, ?string $salesChannelId) directly.</p>

<p><strong>PATTERNS</strong><br />
Build-In patterns are the following:</p>

<p>increment(&#39;n&#39;): Generates a consecutive number, the value to start with can be defined in the configuration</p>

<p>date(&#39;date&#39;,&#39;date_ymd&#39;): Generates the date by time of generation. The standard format is &#39;y-m-d&#39;. The format can be overwritten by passing the format as part of the pattern. The pattern date_ymd generates a date in the Format 190231. This pattern accepts a PHP Dateformat-String</p>

<p><strong>PATTERN EXAMPLE</strong><br />
Order{date_dmy}_{n} will generate a value like Order310219_5489</p>

<p><strong>ValueGeneratorPattern</strong></p>

<p>The ValueGeneratorPattern is a resolver for a part of the whole pattern configured for a given number range.</p>

<p>The build-in patterns mentioned above have a corresponding pattern resolver which is responsible for resolving the pattern to the correct value.</p>

<p>A ValueGeneratorPattern can easily be added to extend the possibilities for specific requirements.</p>

<p>You only need to derive a class from ValueGeneratorPattern and implement your custom rules to the resolve-method.</p>

<p><strong>IncrementConnector</strong><br />
<br />
The increment pattern is somewhat special because it needs to communicate with a persistence layer in some way.</p>

<p>The IncrementConnector allows you to overwrite the connection interface for the increment pattern to switch to a more perfomant solution for this specific task.</p>

<p>If you want to overwrite the IncrementConnector you have to implement the IncrementConnectorInterface in your new connector class and register your new class with the id of the interface.</p>

<p>&nbsp;</p>

<pre>
&lt;service class=&quot;MyNewIncrementConnector&quot; id=&quot;Shopware\Core\System\NumberRange\ValueGenerator\IncrementConnectorInterface&quot;&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;tag name=&quot;shopware.value_generator_connector&quot;/&gt;
&lt;/service&gt;</pre>
