<h2>February 2019</h2>

<h3>2019-02-28: Dynamic Form Field Renderer</h3>

<p>We have a new component for dynamic rendering of form fields. This component is useful whenever you want to render forms based on external configurations or user configuration(e.g. custom fields).</p>

<p><strong>Here are some examples:</strong></p>

<pre>
* {# Datepicker #}
* &lt;sw-form-field-renderer
* &nbsp; &nbsp; &nbsp; &nbsp; type=&quot;datetime&quot;
* &nbsp; &nbsp; &nbsp; &nbsp; v-model=&quot;yourValue&quot;&gt;
* &lt;/sw-form-field-renderer&gt;
*
* {# Text field #}
* &lt;sw-form-field-renderer
* &nbsp; &nbsp; &nbsp; &nbsp; type=&quot;string&quot;
* &nbsp; &nbsp; &nbsp; &nbsp; v-model=&quot;yourValue&quot;&gt;
* &lt;/sw-form-field-renderer&gt;
*
* {# sw-colorpicker #}
* &lt;sw-form-field-renderer
* &nbsp; &nbsp; &nbsp; &nbsp; componentName=&quot;sw-colorpicker&quot;
* &nbsp; &nbsp; &nbsp; &nbsp; type=&quot;string&quot;
* &nbsp; &nbsp; &nbsp; &nbsp; v-model=&quot;yourValue&quot;&gt;
* &lt;/sw-form-field-renderer&gt;
*
* {# sw-number-field #}
* &lt;sw-form-field-renderer
* &nbsp; &nbsp; &nbsp; &nbsp; config=&quot;{
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; componentName: &#39;sw-field&#39;,
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; type: &#39;number&#39;,
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; numberType:&#39;float&#39;
* &nbsp; &nbsp; &nbsp; &nbsp; }&quot;
* &nbsp; &nbsp; &nbsp; &nbsp; v-model=&quot;yourValue&quot;&gt;
* &lt;/sw-form-field-renderer&gt;
*
* {# sw-select - multi #}
* &lt;sw-form-field-renderer
* &nbsp; &nbsp; &nbsp; &nbsp; :config=&quot;{
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; componentName: &#39;sw-select&#39;,
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; label: {
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &#39;en-GB&#39;: &#39;Multi Select&#39;
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; },
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; multi: true,
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; options: [
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { id: &#39;option1&#39;, name: { &#39;en-GB&#39;: &#39;One&#39; } },
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { id: &#39;option2&#39;, name: &#39;Two&#39; },
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { id: &#39;option3&#39;, name: { &#39;en-GB&#39;: &#39;Three&#39;, &#39;de-DE&#39;: &#39;Drei&#39; } }
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ]
* &nbsp; &nbsp; &nbsp; &nbsp; }&quot;
* &nbsp; &nbsp; &nbsp; &nbsp; v-model=&quot;yourValue&quot;&gt;
* &lt;/sw-form-field-renderer&gt;
*
* {# sw-select - single #}
* &lt;sw-form-field-renderer
* &nbsp; &nbsp; &nbsp; &nbsp; :config=&quot;{
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; componentName: &#39;sw-select&#39;,
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; label: &#39;Single Select&#39;,
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; options: [
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { id: &#39;option1&#39;, name: { &#39;en-GB&#39;: &#39;One&#39; } },
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { id: &#39;option2&#39;, name: &#39;Two&#39; },
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; { id: &#39;option3&#39;, name: { &#39;en-GB&#39;: &#39;Three&#39;, &#39;de-DE&#39;: &#39;Drei&#39; } }
* &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; ]
* &nbsp; &nbsp; &nbsp; &nbsp; }&quot;
* &nbsp; &nbsp; &nbsp; &nbsp; v-model=&quot;yourValue&quot;&gt;
* &lt;/sw-form-field-renderer&gt;</pre>

<p><strong>@description - Workflow</strong><br />
Dynamically renders components. To find out which component to render it first checks for the componentName prop. Next it checks the configuration for a componentName. If a componentName isn&#39;t specified, the type prop will be checked to automatically guess a suitable component for the type. Everything inside the config prop will be passed to the rendered child prop as properties. Also all additional props will be passed to the child.</p>

<h3>2019-02-26 : Auto configured repositories</h3>

<p>We implemented a compiler pass which configures all entity repositories automatically.</p>

<p>The compiler pass iterates all configured EntityDefinition and creates an additionally service for the EntityRepository.</p>

<p>The repository is available over the service id {entity_name}.repository.</p>

<p>If a repository is already registered with this service id, the compiler pass skips the definition</p>

<h3>2019-02-25 : Moved the sidebar component to the global page component</h3>

<p>The sidebar component is now placed in the global page component and was removed from the grid and the card-view. This makes the usage of the sidebar consistent in all pages.</p>

<p>All existing pages were updated in the same PR to match the new structure.</p>

<h3>2019-02-25 : Type-hinting for collections</h3>

<p>We recently have introduced a way to prevent mixing of class types in a collection. Now we are adding some sugar based on <a href="https://github.com/shopware/platform/pull/18">this issue </a>on github.</p>

<p>With this change, your IDE will detect the type of the collection and provides the correct type hints when used in foreach loops or when calling the add() method, etc.</p>

<p>In case you&#39;re creating a new collection class, please implement getExpectedClass() because this will be the prerequisite for automatically adding the needed doc block above the class.</p>

<pre>
&lt;?php

/**
* @method void &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;add(PluginEntity $entity)
* @method PluginEntity[] &nbsp; &nbsp;getIterator()
* @method PluginEntity[] &nbsp; &nbsp;getElements()
* @method PluginEntity|null get(string $key)
* @method PluginEntity|null first()
* @method PluginEntity|null last()
*/
class PluginCollection extends EntityCollection
{
&nbsp;&nbsp; &nbsp;protected function getExpectedClass(): string
&nbsp;&nbsp; &nbsp;{
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;return PluginEntity::class;
&nbsp;&nbsp; &nbsp;}
}</pre>

<p>If you implement a method that co-exists in the doc block, please remove the line from the doc block as it will no longer have an effect in your IDE and report it as error.</p>

<h3>2019-02-22&nbsp;: Feature: Product visibility</h3>

<p>We introduced a new Entity product_visibility which is represented by the \Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition class.</p>

<p>It allows the admin to define in which sales channel a product is visible and in which cases:</p>

<ul>
	<li>only deeplink</li>
	<li>only over search</li>
	<li>everywhere</li>
</ul>

<h3>2019-02-22&nbsp;: Administration: Loading all entities</h3>

<p>We implemented a getAll function inside the EntityStore.js class which allows to load all entities of this store. The function fetches all records of the store via queue pattern. The functionality is equals to the getList function (associations, sorting, ...).</p>

<h3>2019-02-21 : Small changes to core components</h3>

<p><strong>sw-modal</strong><br />
It is now possible to hide the header of the sw-modal.</p>

<pre>
&lt;sw-modal title=&quot;Example&quot; showHeader=&quot;false&quot;&gt;&lt;/sw-modal&gt;&nbsp;</pre>

<p><strong>sw-avatar</strong><br />
Instead of the user&#39;s initials, you can now show a placeholder avatar image (default-avatar-single).</p>

<pre>
&lt;sw-avatar placeholder&gt;&lt;/sw-avatar&gt;</pre>

<p><strong>sw-context-button</strong></p>

<p>Now you can specify an alternative icon for the context button. For example you can insert the &quot;default-action-more-vertical&quot; for the vertical three dots. To make sure the opening context menu is correctly aligned, modify the menu offset.</p>

<pre>
&lt;sw-context-button icon=&quot;default-action-more-vertical&quot; :menuOffsetLeft=&quot;18&quot;&gt;&lt;/sw-context-button&gt;</pre>

<h3>2019-02-21 : PHPUnit - random seeds gets printed</h3>

<p>We now print the seed used to randomize the order of test execution.</p>

<p>Therefor we updated PhpUnit to 8.0.4, so you may have run composer update to see the difference.</p>

<p>When the test run fails you can copy the used seed and start phpunit again with the --random-order-seed option.</p>

<p>This makes the test results reproducable and helps you debug dependencies between test cases.</p>

<h3>2019-02-21 : New &lt;sw-side-navigation&gt; component</h3>

<p>A new base component is ready for usage. It is an alternative to the tabs when the viewport width is large enough. The active page is automatically detected and visualized in the component.</p>

<p><strong>Usage:</strong></p>

<pre>
&lt;sw-side-navigation&gt;

&nbsp;&nbsp; &nbsp;&lt;sw-side-navigation-item&nbsp;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;:to=&quot;{ name: &#39;sw.link.example.page1&#39; }&quot;&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;Page 1
&nbsp;&nbsp; &nbsp;&lt;/sw-side-navigation-item&gt;
&nbsp;&nbsp; &nbsp;
&nbsp;&nbsp; &nbsp;&lt;sw-side-navigation-item&nbsp;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;:to=&quot;{ name: &#39;sw.link.example.page2&#39; }&quot;&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;Page 2
&nbsp;&nbsp; &nbsp;&lt;/sw-side-navigation-item&gt;
&nbsp;&nbsp; &nbsp;
&nbsp;&nbsp; &nbsp;&lt;sw-side-navigation-item&nbsp;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;:to=&quot;{ name: &#39;sw.link.example.page3&#39; }&quot;&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;Page 3
&nbsp;&nbsp; &nbsp;&lt;/sw-side-navigation-item&gt;
&nbsp;&nbsp; &nbsp;
&nbsp;&nbsp; &nbsp;&lt;sw-side-navigation-item&nbsp;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;:to=&quot;{ name: &#39;sw.link.example.page4&#39; }&quot;&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;Page 4
&nbsp;&nbsp; &nbsp;&lt;/sw-side-navigation-item&gt; &nbsp;
&nbsp;&nbsp; &nbsp;
&lt;/sw-side-navigation&gt;</pre>

<p>The &lt;sw-side-navigation-item&gt; works exactly like a router link and can receive the same props.</p>

<p>Important: In the future the component will be combined with the new tabs component which has the same styling. It will be a switchable component with a horizontal and vertical mode.</p>

<h3>2019-02-20 : ESLint disabled by default</h3>

<p>ESLint in the Hot Module Reload mode is now disabled by default. You can re-enable it in your psh.yaml file with ESLINT_DISABLE: &quot;true&quot;.</p>

<p>To keep our rules still applied, we&#39;ve added eslint with &ndash;-fix to our pre-commit hook like we do with PHP files. If there are changes, that cannot be safely fixed by eslint, it will show you an error log. Please fix the shown issues and try to commit again.</p>

<p>In addition, both code styles in PHP and JS will be checked in our CI environment, so don&#39;t try to commit with &ndash;-no-verify.</p>

<h3>2019-02-19 : Configuration changes to sw-datepicker</h3>

<p>In order to prevent conflicts between the type properties of sw-field and sw-datepicker we replaced sw-datepicker&#39;s type with a new property dateType. The new property works similar to the old type property of the datepicker.</p>

<pre>
&lt;sw-field type=&quot;date&quot; dateType=&quot;datetime&quot; ...&gt;&lt;/sw-field&gt;</pre>

<p>Valid values for dateType are:</p>

<ul>
	<li>time</li>
	<li>date</li>
	<li>datetime</li>
	<li>datetime-local</li>
</ul>

<h3>2019-02-18 : New OneToOneAssociationField</h3>

<p>The new OneToOneAssociationField allows to register a 1:1 relation in the DAL.</p>

<p>This is especially important for plugin developers to extend existing entities where the values are stored in separate columns in the database.</p>

<p>Important for the 1:1 relation is to set the RestrictDelete and CascadeDelete.</p>

<p>Furthermore, the DAL always assumes a bi-directional association, so the association must be defined on both sides. Here is an example where a plugin adds another relation to the ProductDefinition:</p>

<pre>
ProductDefinition.php

protected static function defineFields(): FieldCollection
{
&nbsp; &nbsp; return new FieldCollection([
&nbsp;&nbsp; &nbsp; &nbsp; //...
&nbsp;&nbsp; &nbsp; &nbsp; (new OneToOneAssociationField(
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &#39;pluginEntity&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &#39;id&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &#39;product_id&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; PluginEntityDefinition::class,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; false)
&nbsp;&nbsp; &nbsp; &nbsp; )-&gt;addFlags(new CascadeDelete())
]);

</pre>

<p>&nbsp;</p>

<pre>
PluginEntityDefinition.php

protected static function defineFields(): FieldCollection
{
&nbsp; &nbsp; return new FieldCollection([
&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;//...
&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;(new OneToOneAssociationField(
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;&#39;product&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;&#39;product_id&#39;, &nbsp;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;&#39;id&#39;,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;ProductDefinition::class,
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;false)
&nbsp;&nbsp; &nbsp; &nbsp; &nbsp;)-&gt;addFlags(new RestrictDelete())
&nbsp; &nbsp; ]);</pre>

<p>&nbsp;</p>

<h3>2019-02-12 : PHPUnit 8 + PCOV</h3>

<p>In order to get faster CodeCoverage we updated to PHPUnit 8 and installed PCOV on the Docker app container. So please rebuild your container and do a composer install.</p>

<p><strong>PHPUnit 8</strong><br />
Sadly PHPUnit 8 comes with BC-Changes that may necessitate changes in your open PRs. The most important ones:</p>

<ul>
	<li>setUp and tearDown now require you to add a void return typehint</li>
	<li>assertArraySubset is now deprecated. Please no longer use it</li>
</ul>

<p>For a full list of changes please see the official announcement:<a href="http://https://phpunit.de/announcements/phpunit-8.html"> https://phpunit.de/announcements/phpunit-8.html</a></p>

<p><strong>PCOV</strong><br />
The real reason for this change! PCOV generates CodeCoverage <strong>in under 4 minutes</strong> on a docker setup.</p>

<p>If you want to generate coverage inside of your container you need to enable pcov through a temporary ini setting first. As an example this will write coverage information to /coverage:</p>

<pre>
php -d pcov.enabled=1 vendor/bin/phpunit --configuration vendor/shopware/platform/phpunit.xml.dist --coverage-html coverage</pre>

<p>you are developing directly on your machine please take a look at <a href="https://github.com/krakjoe/pcov/blob/develop/INSTALL.md">https://github.com/krakjoe/pcov/blob/develop/INSTALL.md</a> for installation options.</p>

<h3>2019-02-11 :Refactoring of &lt;sw-field&gt; and new url field for ssl switching</h3>

<p><strong>&lt;SW-FIELD&gt; REFACTORING</strong><br />
The sw-field was refactored for simpler usage of suffix, prefix and tooltips. You can use it now simply as props. The suffix and prefix is also slotable for more advanced solutions.</p>

<p>Usage example:</p>

<pre>
&lt;sw-field&nbsp;
&nbsp;&nbsp; &nbsp;type=&quot;text&quot;
&nbsp;&nbsp; &nbsp;label=&quot;Text field:&quot;
&nbsp;&nbsp; &nbsp;placeholder=&quot;Placeholder text&hellip;&quot;
&nbsp;&nbsp; &nbsp;prefix=&quot;Prefix&quot;
&nbsp;&nbsp; &nbsp;suffix=&quot;Suffix&quot;
&nbsp;&nbsp; &nbsp;:copyAble=&quot;false&quot;
&nbsp;&nbsp; &nbsp;tooltipText=&quot;I am a tooltip!&quot;
&nbsp;&nbsp; &nbsp;tooltipPosition=&quot;bottom&quot;
&nbsp;&nbsp; &nbsp;helpText=&quot;This is a help text.&quot;
&gt;
&lt;/sw-field&gt;</pre>

<p><strong>NEW FIELD: &lt;SW-FIELD TYPE=&quot;URL&quot;&gt;</strong><br />
Another news is the new SSL-switch field. It allows the user to type or paste a url and the field shows directly if its a secure or unsecure http connection. The user can also change the url with a switch from a secure to an unsecure connection or the other way around.</p>

<p>The field is extended from the normal sw-field. Hence it also allows to use prefix, tooltips, &hellip;</p>

<p>Usage example:</p>

<pre>
&lt;sw-field&nbsp;
&nbsp;&nbsp; &nbsp;type=&quot;url&quot;
&nbsp;&nbsp; &nbsp;v-model=&quot;theNeededUrl&quot;
&nbsp;&nbsp; &nbsp;label=&quot;URL field:&quot;
&nbsp;&nbsp; &nbsp;placeholder=&quot;Type or paste an url&hellip;&quot;
&nbsp;&nbsp; &nbsp;switchLabel=&quot;The description for the switch field&quot;&gt;
&lt;/sw-field&gt;</pre>

<h3>2019-02-08: &lt;sw-tree&gt; refactoring</h3>

<p>The sw-tree now has a function prop createFirstItem whicht will be calles when there are no items in the tree. This should be used to create an initial item if none are given. All other items shoud be created via functions from the action buttons on each item. e.g.: addCategoryBefore or addCategoryAfter. You&#39;ll have to create these functions for the given case and override the slot actions of the sw-tree-item.</p>

<h3>2019-02-07: Rule documentation</h3>

<p>The rules documentation is now available. You are now able to read how to create your own rules using the shopware/platform! Any feedback is appreciated.</p>

<p>See it at: <a href="https://github.com/shopware/platform/blob/master/src/Docs/60-plugin-system/35-custom-rules.md">https://github.com/shopware/platform/blob/master/src/Docs/60-plugin-system/35-custom-rules.md</a></p>

<h3>2019-02-07: System requirements</h3>

<p>The platform now requires PHP &gt;= 7.2.0. We&#39;ve also included a polyfill library for PHP 7.3 functions, so feel free to use them.</p>

<h3>2019-02-06: Plugin configuration</h3>

<p>It is now possible for plugins to create a configuration. This configuration gets dynamically rendered in the administration, however this feature is not actively used right now. Add a new Resources/config.xml to your plugin. Take a look at this short example:</p>

<pre>
&lt;?xml version=&quot;1.0&quot; encoding=&quot;UTF-8&quot;?&gt;
&lt;config xmlns:xsi=&quot;http://www.w3.org/2001/XMLSchema-instance&quot;
xsi:noNamespaceSchemaLocation=&quot;https://raw.githubusercontent.com/shopware/platform/master/src/Core/System/SystemConfig/Schema/config.xsd&quot;&gt;

&nbsp;&nbsp; &nbsp;&lt;card&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;title&gt;Basic Configuration&lt;/title&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;title lang=&quot;de_DE&quot;&gt;Grundeinstellungen&lt;/title&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;input-field type=&quot;password&quot;&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;name&gt;secret&lt;/name&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;label&gt;Secret token&lt;/label&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;label lang=&quot;de_DE&quot;&gt;Geheim Schl&uuml;ssel&lt;/label&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;helpText&gt;Your secret token for xyz...&lt;/helpText&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;helpText lang=&quot;de_DE&quot;&gt;Dein geheimer Schl&uuml;ssel&lt;/helpText&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;/input-field&gt;
&nbsp;&nbsp; &nbsp;&lt;/card&gt;
&lt;/config&gt;</pre>

<p>The configuration is completely optional and meant to help people create a configuration page for their plugin without requiring any knowledge of templating or the Shopware Administration. Read more about the plugin configuration here.</p>

<h3>2019-02-07: New project setup</h3>

<p>Hey folks, currently, the project setup steps are</p>

<ul>
	<li>checkout shopware/development</li>
	<li>run composer install</li>
	<li>change directory to vendor/shopware/platform</li>
	<li>setup stash as remote</li>
	<li>setup PHPStorm to allow editing files in the vendor folder</li>
</ul>

<p><strong>PROBLEMS</strong><br />
One of the big problems is, that if a new dependency is required to be installed, you may break your current project setup as you&#39;ll loose your history in vendor/shopware/platform because composer will detect changes and restores it to a new checkout from github. You have to do the setup all over again.</p>

<p>And to be honest, the setup process isn&#39;t straightforward.</p>

<p><strong>New project setup</strong><br />
From now on, you don&#39;t have to work in vendor/shopware/platform to make changes. In order to use the new process, follow these instructions:</p>

<ul>
	<li>clone shopware/development</li>
	<li>clone shopware/platform in folder platform in the development root directory</li>
	<li>run composer install</li>
</ul>

<p><strong>WHAT HAS CHANGED?</strong><br />
If the platform directory exists, composer will use it as source for the shopware/platform dependency and symlinks it into vendor/shopware/platform. In PHPStorm, you&#39;ll always work in ./platform for your platform changes. They will be automatically be synced to the vendor directory - because it&#39;s a symlink. :zwinkern: This change will also speed up the CI build time significantly.</p>

<p><strong>UPGRADE FROM CURRENT SETUP</strong><br />
To make sure you can use the new setup:</p>

<ul>
	<li>save your current work (push) in vendor/shopware/platform</li>
	<li>clone shopware/platform into in the development root directory as platform</li>
	<li>remove vendor/ and composer.lock</li>
	<li>run composer install</li>
	<li>
	<h3>2019-02-05: Plugin changelogs</h3>
	</li>
	<li>Changelogs could now be provided by plugins:<br />
	Add a new `CHANGELOG.md` file in the root plugin directory. The content has to look like this:</li>
	<li>
	<pre>
# 1.0.0
- initialized SwagTest
* refactored composer.json

# 1.0.1
- added migrations
* done nothing</pre>
	</li>
	<li>If you want to provide translated changelogs, create a `CHANGELOG-de_DE.md`<br />
	The changelog is optional</li>
	<li>
	<h3>2019-02-04: Sample payment plugin available</h3>
	</li>
	<li>A first prototype of a payment plugin is now available on github <a href="https://github.com/shopwareLabs/SwagPayPal">https://github.com/shopwareLabs/SwagPayPal</a></li>
</ul>

<p>&nbsp;</p>

<h3>2019-02-04: sw-field refactoring</h3>

<p>The sw-field is now a functional component which renders the single compontents based on the supplied type. There are no changes in the behavior.</p>

<p>It is now possible to pass options to the select-type which will be rendered as the options with option.id as the value and option.name as the option-name. If you want to use the select as before with slotted options you now don&#39;t need to set slot=&quot;options&quot; because the options will now be passed via the default slot.</p>

<p>All input-types are now available as single components.</p>

<p>sw-text-field for &lt;input type=&quot;text&quot;</p>

<p>sw-password-field for type=&quot;password&quot;</p>

<p>sw-checkbox-field for type=&quot;checkbox&quot;</p>

<p>sw-colorpicker for a colorpicker</p>

<p>sw-datepicker for a datepicker. Here you can pass type time, date, datetime and datetime-local to get the desired picker.</p>

<p>sw-number-field for an input which supports numberType int and float and the common type=&quot;number&quot; params lik step, min and max.</p>

<p>sw-radio-field for type=&quot;radio&quot; with options for each radio-button where option.value is the value and option.name is the label for each field</p>

<p>sw-select-field for &lt;select&gt; where the usage is as described above.</p>

<p>sw-switch-field for type=&quot;checkbox&quot; with knob-styling (iOS like).</p>

<p>sw-textarea-field for &lt;textarea&gt;</p>

<p>sw-field should be used preferably. Single components should be used to save perforamnce in given cases.</p>

<h3>2019-02-01: Storefront building pipeline</h3>

<p>Shopware 6&nbsp;Storefront Building Pipline provides the developer with the ability to use a Node.js based tech stack to build the storefront.</p>

<p><strong>This has many advantages:</strong></p>

<ul>
	<li>Super fast building and rebuilding speed</li>
	<li>Hot Module Replacement</li>
	<li>Automatic polyfill detection based on a browser list</li>
	<li>Additional CSS processing for example automatic generation of vendor prefixes</li>
	<li>The building pipeline is based on Webpack including a dozen plugins. In the following we&#39;re talking a closer look on the pipeline:</li>
</ul>

<p><strong>JS Compilation</strong></p>

<ul>
	<li>babel 7 including babel/preset-env for the ES6-to-ES5 transpilation</li>
	<li>eslint including eslint-recommended rule set for JavaScript linting</li>
	<li>terser-webpack-plugin for the minification</li>
	<li>&nbsp;</li>
	<li><strong>CSS Compilation</strong></li>
	<li><br />
	sass-loader as SASS compiler<br />
	postcss-loader for additional CSS processing<br />
	autoprefixer for the automatic generation of vendor prefixes<br />
	pxtorem for automatic transformation from pixel to rem value<br />
	stylelint for SCSS styles linting based on stylelint-config-sass-guidelines</li>
</ul>

<p><strong>Hot Module Replacement Server</strong></p>

<ul>
	<li>based on Webpack&#39;s devServer</li>
	<li>Overlay showing compilation as well as linting errors right in the browser</li>
</ul>

<p><strong>Additional tooling</strong></p>

<p>friendly-errors-webpack-plugin for a clean console output while using the Hot Module Replacement Server</p>

<ul>
	<li>webpack-bundle-analyzer for analyizing the bundle structure and finding huge packages which are impacting the client performance</li>
	<li>&nbsp;</li>
</ul>

<p><strong>Installation</strong><br />
All commands which are necessary for storefront development can be accessed from the root directory of your shopware instance. The storefront commands are prefixed with storefront:</p>

<pre>
./psh.phar storefront:{COMMAND}</pre>

<p><a href="https://github.com/shopwareLabs/psh">Find out more about about PSH.</a></p>

<p><strong>INSTALL DEPENDENCIES</strong><br />
To get going you first need to install the development dependencies with the init command:</p>

<pre>
./psh.phar storefront:install</pre>

<p>This will install all necessary dependencies for your local environment using <a href="https://www.npmjs.com/">NPM</a>.</p>

<p><strong>Development vs. production build</strong></p>

<p>The development build provides you with an uncompressed version with source maps. The production build on the other hand minifies the JavaScript, combines it into a single file as well as compresses the CSS and combines it into a single file.</p>

<p>The linting of JavaScript and SCSS files is running in both variants.</p>

<p><strong>DEVELOPMENT BUILD</strong></p>

<pre>
./psh.phar storefront:dev</pre>

<p><strong>PRODUCTION BUILD</strong></p>

<pre>
./psh.phar storefront:prod
</pre>

<p><strong>Hot module replacement</strong></p>

<p>The hot module replacement server is a separate node.js server which will be spawned and provides an additional websocket endpoint which pushes updates right to the client. Therefore you don&#39;t have to refresh the browser anymore.</p>

<pre>
./psh.phar storefront:watch</pre>

<h2>January 2019</h2>

<h3>2019-01-31: Roadmap update</h3>

<p>Here you will find a current overview of the epics that are currently being implemented, which have been completed and which will be implemented next.</p>

<p><strong>Open</strong><br />
Work on these Epics has not yet begun.</p>

<ul>
	<li>Theme Manager</li>
	<li>Tags</li>
	<li>Product Export</li>
	<li>First Run Wizard</li>
	<li>Backend Search</li>
	<li>Caching</li>
	<li>Sales Channel</li>
	<li>Additional Basket Features</li>
	<li>Shipping / Payment</li>
	<li>Import / Export</li>
	<li>Mail Templates</li>
	<li>Installer / Updater</li>
	<li>SEO Basics</li>
	<li>Newsletter Integeration</li>
</ul>

<p><strong>Next</strong><br />
These epics are planned as the very next one</p>

<ul>
	<li>Documents</li>
	<li>Custom Fields</li>
	<li>Plugin Manager</li>
	<li>Customer</li>
	<li>Core Settings</li>
	<li>&nbsp;</li>
	<li><strong>In Progress</strong></li>
</ul>

<p>These Epics are in the implementation phase</p>

<ul>
	<li>Products</li>
	<li>Variants / Properties</li>
	<li>SalesChannel API / Page, Pagelets</li>
	<li>Order</li>
	<li>CMS</li>
	<li>Categories</li>
	<li>Product Streams</li>
	<li>ACL</li>
	<li>Background processes</li>
</ul>

<p><strong>Review</strong><br />
All Epics listed here are in the final implementation phase and will be reviewed again.</p>

<ul>
	<li>Rule Builder</li>
	<li>Plugin System</li>
	<li>Snippets</li>
</ul>

<p><strong>Done</strong><br />
These epics are finished</p>

<ul>
	<li>Media Manager</li>
	<li>Content Translations</li>
	<li>Supplier</li>
</ul>

<h3>2019-01-29: LESS becomes SAAS</h3>

<p>We changed the core styling of the shopware administration from LESS to SASS/SCSS. We did that because the shopware storefront will also have SCSS styling with Bootstrap in the future and we wanted to have a similar code style.</p>

<p><strong>Do we use the SASS or SCSS syntax?</strong><br />
We use SCSS! When it comes to brackets and indentations everything stays the same. For comparison: <a href="https://sass-lang.com/guide">https://sass-lang.com/guide </a>(You can see a syntax switcher inside the code examples)</p>

<p><strong>What if my whole module or plugin is still using LESS?</strong><br />
This should have no effect in the first place because SCSS is only an addition. All Vue components do support both LESS and SCSS. All LESS variables and mixins are still available for the moment in order to prevent plugins from breaking. When all plugins are migrated to SCSS styles we can get rid of the LESS variables and mixins.</p>

<p><strong>How do I change my LESS to SCSS?</strong></p>

<ul>
	<li><strong>Run administration:init</strong>

	<ul>
		<li>The new SASS has to be installed first.</li>
	</ul>
	</li>
	<li><strong>Change file extension from .less to .scss</strong>
	<ul>
		<li>Please beware of the import inside the index.js file.</li>
	</ul>
	</li>
	<li><strong>Change the alias inside the style imports:</strong>
	<ul>
		<li>The alias inside the style imports changes from ~less to ~scss:</li>
	</ul>
	</li>
	<li>
	<pre>
// Old
@import &#39;~less/variables&#39;;

// New
@import &#39;~scss/variables&#39;;</pre>

	<ul>
		<li><strong>Change variable prefixes:</strong><br />
		Variable prefixes has to be changed from @ to $:</li>
		<li>
		<pre>
// Old
color: @color-shopware;

// New
color: $color-shopware;</pre>
		</li>
		<li>
		<p>If you do a replace inside your IDE, please take care of the Style Imports as well as the MediaQueries.</p>

		<p>All base variables have been migrated to SCSS and can be used as before.</p>
		</li>
		<li>
		<p><strong>Change mixin calls:</strong></p>
		</li>
		<li>
		<pre>
// Old
.truncate();

// New
@include truncate();</pre>
		</li>
	</ul>
	</li>
</ul>

<h3>2019-01-29: Clone entities</h3>

<p>It is now possible to clone entities in the system via the following endpoint:</p>

<p>/api/v1/_action/clone/{entity}/{id}</p>

<p>As a response you will get the new id</p>

<p>{ id: &quot;a3ad........................................ }</p>

<p><strong>What will be cloned with the entity?</strong></p>

<ul>
	<li>OneToMany associations marked with CascadeDelete flag</li>
	<li>ManyToMany associations (here only the mapping tables entries)</li>
	<li>For example product N:M category (mapping: product_category)</li>
</ul>

<p>The category entities are not cloned with product_category entries are cloned</p>

<h3>2019-01-29: Object cache</h3>

<p>The cache can be referenced at `cache.object`. Here you find a \Symfony\Component\Cache\Adapter\TagAwareAdapterInterface behind. This allows additional tags to be stored on a CacheItem:</p>

<pre>
$item = $this-&gt;cache-&gt;getItem(&#39;test&#39;);
$item-&gt;tag([&#39;a&#39;, &#39;b&#39;];
$item-&gt;set(&#39;test&#39;)
$this-&gt;cache-&gt;save($item);</pre>

<p>What do we use the cache for?</p>

<ul>
	<li>Caching Entities</li>
	<li>Caching from Entity Searches</li>
</ul>

<p>Where is the caching located in the core?</p>

<pre>
\Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntityReader
\Shopware\Core\Framework\DataAbstractionLayer\Cache\CachedEntitySearcher</pre>

<p>When do I have to consider the cache?</p>

<ul>
	<li>In all indexers, i.e. whenever you write directly to the database</li>
</ul>

<p>Here you can find an example \Shopware\Core\Content\Rule\DataAbstractionLayer\Indexing\RulePayloadIndexer</p>

<h3>2019-01-29: sw-button new features</h3>

<p>The sw-button component has been extended by some smaller features.</p>

<ul>
	<li>Square Button (For buttons which only contain an icon)</li>
	<li>Button Group (Shows buttons in a &quot;button bar&quot; without spacing in between)</li>
	<li>Split Button (A combination of Button Group, Square Button and Context Menu)</li>
</ul>

<p><img alt="" src="https://sbp-testingmachine.s3.eu-west-1.amazonaws.com/1552462731/slack-imgs.png" />Here are some code examples which show you how to use the new features:</p>

<pre>
&lt;!-- Square buttons --&gt;
&lt;sw-button square size=&quot;small&quot;&gt;
&nbsp;&nbsp; &nbsp;&lt;sw-icon name=&quot;small-default-x-line-medium&quot; size=&quot;16&quot;&gt;&lt;/sw-icon&gt;
&lt;/sw-button&gt;
&lt;sw-button square size=&quot;small&quot; variant=&quot;primary&quot;&gt;
&nbsp;&nbsp; &nbsp;&lt;sw-icon name=&quot;small-default-checkmark-line-medium&quot; size=&quot;16&quot;&gt;&lt;/sw-icon&gt;
&lt;/sw-button&gt;

&lt;!-- Default button group --&gt;
&lt;sw-button-group splitButton&gt;
&nbsp;&nbsp; &nbsp;&lt;sw-button-group&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;sw-button&gt;Button 1&lt;/sw-button&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;sw-button&gt;Button 2&lt;/sw-button&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;sw-button&gt;Button 3&lt;/sw-button&gt;
&nbsp;&nbsp; &nbsp;&lt;/sw-button-group&gt;
&lt;/sw-button-group&gt;

&lt;!-- Primary split button with context menu --&gt;
&lt;sw-button-group splitButton&gt;
&nbsp;&nbsp; &nbsp;&lt;sw-button variant=&quot;primary&quot;&gt;Save&lt;/sw-button&gt;
&nbsp;&nbsp; &nbsp;
&nbsp;&nbsp; &nbsp;&lt;sw-context-button&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;sw-button square slot=&quot;button&quot; variant=&quot;primary&quot;&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;sw-icon name=&quot;small-arrow-medium-down&quot; size=&quot;16&quot;&gt;&lt;/sw-icon&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;/sw-button&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;sw-context-menu-item&gt;Save and exit&lt;/sw-context-menu-item&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;sw-context-menu-item&gt;Save and publish&lt;/sw-context-menu-item&gt;
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&lt;sw-context-menu-item variant=&quot;danger&quot;&gt;Delete&lt;/sw-context-menu-item&gt;
&nbsp;&nbsp; &nbsp;&lt;/sw-context-button&gt;
&lt;/sw-button-group&gt;</pre>

<h3>2019-01-29: Automatic generation of api services based on entity scheme</h3>

<p>We changed the handling of API services. The services are now generated automatically based on the entity scheme.</p>

<p>It&#39;s still possible to create custom API serivces. To do so, as usual, create a new file in the directory src/core/service/api. You don&#39;t have to deal with the registration of these services - the administration will automatically import and register the service into the application for you.</p>

<p>Something has changed though - the base API serivce is located under src/core/service instead of src/core/service/api.</p>

<p>There&#39;s something you have to keep in mind tho. Please switch the pfads accordingly and custom API services are needing a name property which represents the name the application uses to register the service.</p>

<p>Here&#39;s an example CustomerAddressApiService:</p>

<pre>
// Changed import path
import ApiService from &#39;../api.service&#39;;

/**
&nbsp;* Gateway for the API end point &quot;customer_address&quot;
&nbsp;* @class
&nbsp;* @extends ApiService
&nbsp;*/
class CustomerAddressApiService extends ApiService {
&nbsp; &nbsp; constructor(httpClient, loginService, apiEndpoint = &#39;customer_address&#39;) {
&nbsp; &nbsp; &nbsp; &nbsp; super(httpClient, loginService, apiEndpoint);
&nbsp; &nbsp;&nbsp;
&nbsp; &nbsp; &nbsp; &nbsp; // Name of the service
&nbsp; &nbsp; &nbsp; &nbsp; this.name = &#39;customerAddressService&#39;;
&nbsp; &nbsp; }

&nbsp; &nbsp; // ...
}</pre>

<p>&nbsp;</p>

<h3>2019-01-29: Feature flags</h3>

<p>In Shopware 6 you can switch off features via environment variables and also merge &quot;Work in Progress&quot; changes into the master. So how does this work?</p>

<p><strong>Create</strong><br />
When you start developing a new feature, you should first create a new flag. As a convention we use a Jira reference number here. Remember, this will be published to GitHub, so just take the issue number.</p>

<pre>
bin/console feature:add NEXT-1128
</pre>

<p><strong>Creates</strong></p>

<pre>
application@d162c25ff86e:/app$ bin/console feature:add NEXT-1128

Creating feature flag: NEXT-1128
==============================================

&nbsp;---------- --------------------------------------------------------------------------------------------------------------&nbsp;
&nbsp; Type &nbsp; &nbsp; &nbsp; Value &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;
&nbsp;---------- --------------------------------------------------------------------------------------------------------------&nbsp;
&nbsp; PHP-Flag &nbsp; /app/components/platform/src/Core/Flag/feature_next1128.php &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;
&nbsp; JS-Flag &nbsp; &nbsp;/app/components/platform/src/Administration/Resources/administration/src/flag/feature_next1128.js &nbsp;
&nbsp; Constant &nbsp; FEATURE_NEXT_1128 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;
&nbsp;---------- --------------------------------------------------------------------------------------------------------------&nbsp;

&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;
&nbsp;[OK] Created flag: NEXT-1128 &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;
&nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp; &nbsp;&nbsp;

&nbsp;! [NOTE] Please remember to add and commit the files&nbsp;</pre>

<p>After that you should make a git add to add the new files.</p>

<p><strong>Enable</strong><br />
The system disables all flags per default. To switch the flags on you can simply add them to your .psh.yaml.override. An example might look like this:</p>

<pre>
const:
&nbsp; FEATURES: |
&nbsp; &nbsp; FEATURE_NEXT_1128=1</pre>

<p>This is automatically written to the .env file and from there imported into the platform.</p>

<p><strong>USAGE IN PHP</strong><br />
The interception points in the order of their usefulness:</p>

<pre>
&lt;service ...&gt;
&nbsp; &nbsp;&lt;tag name=&quot;shopware.feature&quot; flag=&quot;next1128&quot;/&gt;
&lt;/service&gt;</pre>

<p>If possible, you should be able to toggle your additional functionality over the DI container. The service exists only if the flag is enabled.</p>

<p>Everything else is implemented in the form of PHP functions. These are created through the feature:add command.</p>

<pre>
use function Flag\skipTestNext1128NewDalField;

class ProductTest
{
&nbsp; public function testNewFeature()&nbsp;
&nbsp; {
&nbsp; &nbsp; &nbsp;skipTestNext1128NewDalField($this);

&nbsp; &nbsp; &nbsp;// test code
&nbsp; }
}</pre>

<p>If you customize a test, you can flag it by simply calling this function. Also works in setUp</p>

<p>If there is no interception point through the container, you can use other functions:</p>

<pre>
use function Flag\ifNext1128NewDalFieldCall;
class ApiController
{

&nbsp; public function indexAction(Request $request)
&nbsp; {
&nbsp; &nbsp; // some old stuff
&nbsp; &nbsp; ifNext1128NewDalFieldCall($this, &#39;handleNewFeature&#39;, $request);
&nbsp; &nbsp; // some old stuff
&nbsp; }

&nbsp; private function handleNewFeature(Request $request)
&nbsp; {
&nbsp; &nbsp; // awesome new stuff
&nbsp; }
}</pre>

<p>Just create your own, private, method in which you do the new stuff, nobody can mess with you!</p>

<pre>
use function Flag\ifNext1128NewDalField;
class ApiController
{

&nbsp; public function indexAction(Request $request)
&nbsp; {
&nbsp; &nbsp; // some old stuff
&nbsp; &nbsp; ifNext1128NewDalField(function() use ($request) {
&nbsp; &nbsp; &nbsp; // awesome stuff
&nbsp; &nbsp; });
&nbsp; &nbsp; // some old stuff
&nbsp; }

}</pre>

<p>If this seems like &#39;too much&#39; to you, use a callback to connect your new function. Also here it will be hard for others to mess you up.</p>

<pre>
use function Flag\next1128NewDalField;
class ApiController
{
&nbsp; public function indexAction(Request $request)
&nbsp; {
&nbsp; &nbsp; // some old stuff
&nbsp; &nbsp; if (next1128NewDalField()) {
&nbsp; &nbsp; &nbsp; //awesome new stuff
&nbsp; &nbsp; }
&nbsp; &nbsp; // some old stuff
&nbsp; }
}</pre>

<p>If there is really no other way, there is also a simple function that returns a Boool. Should really only happen in an emergency, because you&#39;re in the same scope as everyone else. So other flags can easily overwrite your variables.</p>

<p><strong>USAGE IN THE ADMIN SPA</strong><br />
This works very similar to the PHP hook points. The preferred interception points are only slightly different though</p>

<pre>
&lt;sw-field type=&quot;text&quot;
&nbsp;&nbsp; &nbsp;...
&nbsp;&nbsp; &nbsp;v-if=&quot;next1128NewDalField&quot;
&nbsp;&nbsp; &nbsp;...&gt;
&lt;/sw-field&gt;</pre>

<p>To simply hide an element, use v-if with the name of your flag. This is always registererd and defaults to false. In this case the whole component will not even be instantiated.</p>

<pre>
import { NEXT1128NEWDALFIELD } from &#39;src/flag/feature_next1128NewDalField&#39;;

Module.register(&#39;sw-awesome&#39;, {
&nbsp;&nbsp; &nbsp;flag: NEXT1128NEWDALFIELD,
&nbsp;&nbsp; &nbsp;...
});</pre>

<p>With this you can remove a whole module from the administration pannel.</p>

<p>If these intervention points are not sufficient the functions from PHP are also available - almost 1:1.</p>

<pre>
import {
&nbsp; &nbsp;ifNext1128NewDalField,
&nbsp; &nbsp;ifNext1128NewDalFieldCall,
&nbsp; &nbsp;next1128NewDalField
} from &quot;src/flag/feature_next1128NewDalField&quot;;

ifNext1128NewDalFieldCall(this, &#39;changeEverything&#39;);

ifNext1128NewDalField(() =&gt; {
&nbsp; &nbsp;// something awesome
});

if (next1128NewDalField) {
&nbsp; &nbsp;// something awesome
}</pre>

<p>These can also be used freely in the components. However, the warnings from the PHP part also apply here!</p>

<h3>2019-01-29: Symfony service naming</h3>

<p>Until now, we have always used the following format for service definitions:</p>

<p>&lt;service class=&quot;Shopware\Core\Checkout\Cart\Storefront\CartService&quot; id=&quot;Shopware\Core\Checkout\Cart\Storefront\CartService&quot;/&gt;</p>

<p>The reason for this was that PHPStorm could only resolve the class and the ID was not recognized as a class. Therefore we maintained the two parameters. This is no longer a problem. Therefore we changed the platform repo and development template. The new format now looks like this:</p>

<p>&lt;service id=&quot;Shopware\Core\Checkout\Cart\Storefront\CartService&quot;/&gt;</p>

<p>There is also a test which enforces the new format.</p>

<h3>2019-01-29: Entity changes</h3>

<p>Each entity now has the getUniqueIdentifier and setUniqueIdentifier methods necessary for the DAL. The uniqueIdentfier is the first step to support multi column primary keys.</p>

<p>The getId/setId and Property $id methods are no longer implemented by default, but can be easily added with the EntityIdTrait. This default implementation automatically sets the uniqueIdentifier, which has to be set for a manual implementation.</p>

<h3>2019-01-29: System language</h3>

<p>There is now a system language that serves as the last fallback. At the moment it is still hardcoded en_GB. This should be configurable in the future. Important: If you create new entities, you must always provide a translation for Defaults::LANGUAGE_SYSTEM.</p>

<p>The constant Defaults::LANGUAGE_SYSTEM replaces Defaults::LANGUAGE_EN, which is now deprecated. Please exchange this everywhere. Since there can be a longer translation chain now, it is now also stored as an array in the context. Context::getFallbackLanguageId was removed, instead there is Context::getLanguageIdChain.</p>

<h3>2019-01-29: EntityTranslationDefinition simplification</h3>

<p>Changed defineFields to only define the translated fields. Primary key, Foreign key und die standard associations are determined automatically. But EntityTranslationDefinition::getParentDefinitionClass (previously called getRootEntity) is no longer optional.</p>

<p><strong>Before</strong>:</p>

<pre>
class OrderStateTranslationDefinition extends EntityTranslationDefinition
{
&nbsp;&nbsp; &nbsp;public static function defineFields(): FieldCollection
&nbsp;&nbsp; &nbsp;{
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;return new FieldCollection([
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;(new FkField(&#39;order_state_id&#39;, &#39;orderStateId&#39;, OrderStateDefinition::class))-&gt;setFlags(new PrimaryKey(), new Required()),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;(new ReferenceVersionField(OrderStateDefinition::class))-&gt;setFlags(new PrimaryKey(), new Required()),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;(new FkField(&#39;language_id&#39;, &#39;languageId&#39;, LanguageDefinition::class))-&gt;setFlags(new PrimaryKey(), new Required()),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;(new StringField(&#39;description&#39;, &#39;description&#39;))-&gt;setFlags(new Required()),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;new CreatedAtField(),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;new UpdatedAtField(),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;new ManyToOneAssociationField(&#39;orderState&#39;, &#39;order_state_id&#39;, OrderStateDefinition::class, false),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;new ManyToOneAssociationField(&#39;language&#39;, &#39;language_id&#39;, LanguageDefinition::class, false),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;]);
&nbsp;&nbsp; &nbsp;}
&nbsp;&nbsp; &nbsp;public static function getRootEntity(): ?string
&nbsp;&nbsp; &nbsp;{
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;return OrderStateDefinition::class;
&nbsp;&nbsp; &nbsp;}
}</pre>

<p><strong>After</strong>:</p>

<pre>
class OrderStateTranslationDefinition extends EntityTranslationDefinition
{
&nbsp;&nbsp; &nbsp;public static function getParentDefinitionClass(): string
&nbsp;&nbsp; &nbsp;{
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;return OrderStateDefinition::class;
&nbsp;&nbsp; &nbsp;}
&nbsp;&nbsp; &nbsp;protected static function defineFields(): FieldCollection
&nbsp;&nbsp; &nbsp;{
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;return new FieldCollection([
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;(new StringField(&#39;description&#39;, &#39;description&#39;))-&gt;setFlags(new Required()),
&nbsp;&nbsp; &nbsp;&nbsp;&nbsp; &nbsp;]);
&nbsp;&nbsp; &nbsp;}
}</pre>

<p>In addition, all defineFields methods have been set to protected, since they should only be called internally.</p>

<h3>2019-01-29: Collection Classes</h3>

<p>The collection classes have been cleaned up and a potential bug has been resolved. Since there are no generics in PHP, you can overwrite the method getExpectedClass() and specify a type in the derived classes. The value is checked on add() and set() and throws an exception if an error occurs. If you want to use your own logic for the keys, overwrite the add() method.</p>

<p>Additionally the IteratorAggregate interface has been implemented.</p>

<h3>2019-01-29: TranslationEntity</h3>

<p>After the EntityTranslationDefinition has made its way into the system to save boilerplate code, we continue with the Entities.</p>

<p>There is now the TranslationEntity class, which already contains the boilerplate code (properties and methods) and only needs to be extended by its own fields and the relation. <a href="https://github.com/shopware/platform/blob/master/src/Core/Checkout/Customer/Aggregate/CustomerGroupTranslation/CustomerGroupTranslationEntity.php">Here is an example!</a></p>

<h3>2019-01-29: Color-Picker component</h3>

<p>There is a new form component, namely the &lt;sw-color-picker&gt; . With the colorpicker it is possible to select a hex string with a colorpicker UI.</p>

<p>Here is a small example how this can look like in Action:</p>

<pre>
&lt;sw-color-picker
&nbsp;&nbsp; &nbsp;label=&quot;My Color&quot;
&nbsp;&nbsp; &nbsp;:disabled=&quot;disabled&quot;
&nbsp;&nbsp; &nbsp;v-model=&quot;$route.meta.$module.color&quot;&gt;
&lt;/sw-color-picker&gt;</pre>

<h3>2019-01-29: Refactoring plugin system</h3>

<ul>
	<li>To update plugins in the system execute bin/console plugin:refresh
	<ul>
		<li>all Lifecycle commands call the refresh method before, so you don&#39;t need execute the refresh command before the installation of a plugin</li>
	</ul>
	</li>
	<li>a Pre and Post event is fired for every change of the lifecycle state of a plugin</li>
	<li>Every plugin now needs a valid composer.json in the pluugin root directory
	<ul>
		<li>Have a look here, how it have to look like: src/Docs/60-plugin-system/05-plugin-information.md</li>
	</ul>
	</li>
</ul>
