[titleEn]: <>(Plugin system: New flag managed_by_composer on plugin table)
[__RAW__]: <>(__RAW__)

<p>The entity for plugins got a new boolean field <strong>managedByComposer</strong> which determines if a plugin is required with composer. The field is set during <strong>bin/console plugin:refresh</strong></p>

<p>So if you are currently developing or working with plugins you might need to recreate your database.</p>
