[titleEn]: <>(Plugin - Commands)
[hash]: <>(article:plugin_commands)

## Listing
In this guide, you'll learn which `Plugin Commands` come with `Shopware` and what they are used for.
Below you'll find a list of each command and its usage.

| Command           | Arguments | Options                         | Usage                                                                  |
|-------------------|-----------|---------------------------------|------------------------------------------------------------------------|
| plugin:create     | name      | N/A                             | Creates a skeleton Plugin with the given name
| plugin:install    | plugins   | activate, reinstall, clearCache | Installs, re-installs and activates one or multiple plugins            |
| plugin:uninstall  | plugins   | keep-user-data, clearCache      | Uninstalls one or multiple plugins                                     |
| plugin:activate   | plugins   | clearCache                      | Activates one or multiple installed plugins                            |
| plugin:deactivate | plugins   | clearCache                      | Deactivates one or multiple installed plugins                          |
| plugin:update     | plugins   | clearCache                      | Updates one or multiple plugins                                        |
| plugin:list       | N/A       | filter                          | Prints a list of all available plugins filtering with the given filter |
| plugin:refresh    | N/A       | skipPluginList                  | Refreshes the plugin list                                              |
| plugin:zip-import | zip-file  | no-refresh                      | Import plugin zip file                                                 |

*List of all plugin commands*

Please note, that arguments are always required and options are optional.

After activating, deactivating, updating or uninstalling your plugin you have to clear the cache, you can use the `clearCache` option for this.
Alternatively you can run the command `bin/console cache:clear`

## Examples

Below you'll find some examples for you to become more familiar with these commands.

```
$ ./bin/console plugin:create YourPluginName
```
*Plugin create command*

The first command will create a plugin skeleton with the given `Name`.
The Plugin skeleton will be found in the `/custom/plugins/` directory. 

```
$ ./bin/console plugin:list

$ ./bin/console plugin:list --filter YourPluginName
```
*Plugin list command*

The first command will list all plugins currently known to `Shopware`.
The second command will list all plugins containing the `YourPluginName` in the plugin name or label.

### Refreshing plugins

Refreshing your plugins is necessary every time you add a new plugin by hand.
Below you'll find a few examples how to use the `plugin:refresh` command.

```
$ ./bin/console plugin:refresh

$ ./bin/console plugin:refresh --skipPluginList

$ ./bin/console plugin:refresh -s
```
*Plugin refresh command*

The first command will refresh the available plugins and print out the refreshed plugin list.
The other two commands will refresh the available plugins without printing the plugin list.

### Installing/uninstalling plugins

You can either install or uninstall one plugin at a time or even a list of plugins at once.
Have a look at the example below to get an idea on how to use the `plugin:install` and `plugin:uninstall` command.

```
$ ./bin/console plugin:install YourPluginName
$ ./bin/console plugin:uninstall YourPluginName

$ ./bin/console plugin:install YourPluginName ThirdPartyPluginName
$ ./bin/console plugin:uninstall YourPluginName ThirdPartyPluginName
```
*Plugin un-, install command*

### Updating plugins

You can either update one plugin at a time or a list of plugins.

```
$ ./bin/console plugin:update YourPluginWithNamespace

$ ./bin/console plugin:update YourPluginWithNamespace ThirdPartyPluginWithNamespace
```
*Plugin update command*

The first command updates the `YourPlugin` plugin if it exists.
The second command updates `YourPlugin` and `ThirdPartyPlugin` if they exist.

### Activating/Deactivating plugins

Activating and deactivating plugins works in a similar fashion.
You can either activate or deactivate one plugin at a time or a list of plugins at once.

```
$ ./bin/console plugin:activate YourPluginWithNamespace
$ ./bin/console plugin:deactivate YourPluginWithNamespace

$ ./bin/console plugin:activate YourPluginWithNamespace ThirdPartyPluginWithNamespace
$ ./bin/console plugin:deactivate YourPluginWithNamespace ThirdPartyPluginWithNamespace
```
*Plugin de-, activate command*

### Importing plugins

There are multiple way to import a plugin that is already existing on the filesystem.
You can either make a composer repository and use composer require:

```js
{
    /* ... */
    "repositories": [
        {
            "type": "path",
            "url": "YourPluginDirectory",
            "options": {
                "symlink": true
            }
        }
    ]
}
```
```
$ composer require your-plugin-technical-name
$ ./bin/console plugin:refresh
```

Or you can provide a pre-packaged plugin file like they are distributed at the [community store](https://store.shopware.com).

```
$ ./bin/console plugin:zip-import YourPluginFile.zip
```
