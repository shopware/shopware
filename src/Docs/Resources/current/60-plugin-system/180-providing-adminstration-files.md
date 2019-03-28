[titleEn]: <>(Providing files for the Administration)
[titleDe]: <>(Providing files for the Administration)
[wikiUrl]: <>(../plugin-system/provide-admin-files?category=shopware-platform-en/plugin-system)

Once you created an [extension for the administration](../10-administration/01-administration-start-development.md#create-your-first-plugin),
whether it is a new module or by extending an existing module,
your plugin has to provide these files to Shopware.
Execute this command to build your files, so they could be used by Shopware.

```bash
$ ./psh.phar administration:build
```

The generated files are now located under `<Plugin-Directory>/Resources/public`.
If a plugin is activated, all the files are copied into the public web directory of Shopware.
After that your extension should work immediately.
If the plugin is deactivated again, the files are deleted again.

Always remember to ship your plugin with these build asset files.
