[titleEn]: <>(sw-icon update)
[__RAW__]: <>(__RAW__)

<p>The icon system in the administration has been updated.</p>

<p>Please execute <strong>administration:install </strong>to install new dependencies.</p>

<p><strong>Usage</strong><br />
The open API of the <strong>&lt;sw-icon&gt; </strong>component has not been changed. You can use it as before.</p>

<p><strong>Adding or updating icons</strong></p>

<ul>
	<li>All SVG icons can now be found in <strong>/platform/src/Administration/Resources/app/administration/src/app/assets/icons/svg</strong> as separate files.</li>
	<li><strong>TLDR: To add a new icon simply add the icon SVG file to the mentioned direcory.</strong></li>
	<li>All icons have to be prefixed with <strong>icons-.</strong></li>
	<li>The file names come from the directory structure of our design library. The export via Sketch automatically gives us a file name like <strong>icons-default-action-bookmark</strong>.</li>
	<li><em>Please keep in mind that these icons are the core icons. Do never add random icons from the web or stuff like that! We always receive the icons from the design department with properly optimized SVG files. When you need a completely new core icon please talk to the design department first.</em></li>
	<li>All icons from this directory are automatically registered as small functional components which are automatically available when using <strong>&lt;sw-icon name=&quot;your-icon-name&quot;&gt;</strong>. The component gets its name from the SVG file name. This is why a correct name is really important.</li>
	<li>When updating an icon simply override the desired SVG file.</li>
</ul>

<p><strong>Icon demo</strong></p>

<ul>
	<li>New demo: <a href="https://component-library.shopware.com/#/icons/">https://component-library.shopware.com/#/icons/</a></li>
	<li>The icon demo is now part of the component library. It can also be found at the very bottom of the main menu. This is the source of truth from now on.</li>
	<li>No more separate demos for default and multicolor icons.</li>
	<li>The icon demo gets updated automatically when icons are added, removed or updated.</li>
</ul>

<p><strong>Chrome bug</strong><br />
The icon bug in Google Chrome has been fixed. The SVG&#39;s source code is now directly inside the document. The use of an external SVG sprite is no longer in place. This caused the rendering issues under some circumstances in Vue.</p>

<p><strong>Why we made this change</strong></p>

<ul>
	<li>Easier workflow to add or update icons</li>
	<li>Inline SVGs do fix the Chrome bug</li>
	<li>No more dependencies of third party grunt tasks to generate the icon sprite</li>
	<li>No grunt dependency to build the icon demo.</li>
	<li>No extra request from the browser to get the icon sprite</li>
	<li>No extra repository required</li>
</ul>
