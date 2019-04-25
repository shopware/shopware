[titleEn]: <>(Plugin structure refactoring)

We made a refactoring of the plugin structure which affect ALL plugins!

* The "type" in the composer.json must now be `shopware-platform-plugin`. This is necessary to differentiate between Shopware 5 and Shopware platform plugins
* You now have to provide the whole FQN of your plugin base class in the composer.json. Add something like this to the "extra" part of the composer.json: `"shopware-plugin-class": "SwagTest\\SwagTest"`, The old identifier `installer-name` is no longer used
* You now have to provide valid autoload information about your plugin with the composer.json:

```json
"autoload": {
	"psr-4": {
		"SwagTest\\": ""
	}
}
```
This give also the opportunity to do something like this:

```json
"autoload": {
	"psr-4": {
		"Swag\\Test\\": "src/"
	}
}
```

Which should really tidy up the root directory of a plugin

* If you want to provide a plugin icon, you have to specify the path of the icon relative to your plugin base class in the composer.json. Add a new field to the "extra" part of the composer.json: `"plugin-icon": "Resources/public/plugin.png",`
* We introduced a default path for the plugin config file. It points to `Resources/config/config.xml` relative from your plugin base class. So if you put your config there, Shopware will automatically generated a configuration form for your plugin. If you want another path, just overwrite the `\Shopware\Core\Framework\Bundle::getConfigPath` method
* We introduced some more defaults path which could all be changed by overwriting the appropriate method. The "Resources" directory is always relative to the base class of your plugin
  * `Resources/config/services.xml` path to your default services.xml to register your custom services
  * `[Resources/views]` Array of views directorys of your plugin
  * `Resources/adminstration` the location of your administration files and entry point of extensions of the administration
  * `Resources/storefront` same for the storefront
  * `Resources/config/` directory which will be used to look for route config files

All in all, the composer.json should contain descriptive information and the plugin base class the runtime configuration
