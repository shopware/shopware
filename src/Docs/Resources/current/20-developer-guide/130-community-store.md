[titleEn]: <>(Community store)
[hash]: <>(article:developer_community_store)

This guide will take you through the steps to get a plugin ready for the community store. Our [Quality Guidelines for Plugins in the Shopware Community Store](https://docs.shopware.com/en/plugin-standard-for-community-store) should also be read very carefully.

# Vendor prefix
Every plugin needs to have a vendor prefix. The vendor prefix should be in the root folder name and has to be in the name of the plugin base class. Here is an example:
```
SwagStorePlugin
├── composer.json
├── src
    └── SwagStorePlugin.php
```

The `Swag` prefix is the vendor prefix in this case(shopware AG). The plugin base class should also have the prefix and can look like this:
`SwagStorePlugin/src/SwagStorePlugin.php`
```php
<?php declare(strict_types=1);

namespace Swag\StorePlugin;

use Shopware\Core\Framework\Plugin;

class SwagStorePlugin extends Plugin
{

}
``` 

# Meta information / composer.json
The `composer.json` file holds all the meta information for your plugin. Here is a minimal example:
`SwagStorePlugin/composer.json`
```
{
    "name": "swag/store-plugin",
    "description": "Store plugin example",
    "type": "shopware-platform-plugin",
    "license": "MIT",
    "version": "1.0.0",
    "authors": [
        {
            "name": "shopware AG"
        }
    ],
    "autoload": {
        "psr-4": {
            "Swag\\StorePlugin\\": "src/"
        }
    },
    "require": {
        "shopware/core": "~6.2.0"
    },
    "extra": {
        "shopware-plugin-class": "Swag\\StorePlugin\\StorePlugin",
        "label": {
            "de-DE": "Store plugin example",
            "en-GB": "Store plugin example"
        },
        "description": {
            "de-DE": "Dies ist ein Community Store Plugin",
            "en-GB": "This is a community store plugin"
        }
    }
}
```
**Important**: 
- A least one require is necessary to pass the validation. This can be `"shopware/core": "~6.2.0"` if the plugin needs the shopware core. It is also possible to require other parts like the administration: `"shopware/administration": "~6.2.0"` or storefront: `"shopware/storefront": "~6.2.0"`. Do not forget to replace the version with the required version for your plugin.
- The license is fully up to you. But be aware, that this example uses the `MIT` license which would allow your customers to use your plugin and release it again with their own name for example.
          
For more information about the available options and more details take a look at the [plugin meta information reference](./../60-references-internals/40-plugins/050-plugin-information.md).

# Icon
The favicon of a plugin is a requirement for a community store plugin. Therefore a 40 x 40 px png file can be shipped with the following path/filename: `SwagStorePlugin/src/Resources/config/plugin.png`. More information in the [plugin meta information reference](./../60-references-internals/40-plugins/050-plugin-information.md).

# Changelog
A changelog is required for every community store plugin. This file contains the version changes of the plugin. It has to be called `CHANGELOG.md` and should at least contain the initial version:
`SwagStorePlugin/CHANGELOG.md`
```markdown
# 1.0.0
- First version of the community store plugin

```
More information can be found in the [plugin changelog guide](./../60-references-internals/40-plugins/060-plugin-changelog.md).

# Packing the plugin
The plugin needs to be packed as a zip file with the right structure in order to work with the community store. Here is an example of the folder structure:
```
SwagStorePlugin
├── composer.json
├── src
    └── SwagStorePlugin.php
```
Be aware that the folder `SwagStorePlugin` should be the **root folder** of the zip file.

# PhpStan and SonarQube
Plugins are automatically checked with PhpStan and SonarQube to ensure a certain code quality. The configurations used during the automatic code review con be found on GitHub: [Code reviews for Shopware 5 and 6 on GitHub](https://github.com/shopwareLabs/store-plugin-codereview).

# Automatic code review fails
If the automatic code review fails for a plugin an error with the reason is provided. We have a list of common errors and their fixes in the [code review section of the plugin quality guidelines](https://docs.shopware.com/en/plugin-standard-for-community-store#code-review-errors)

# Include compiled Javascript files
If your plugin includes Javascript files, please make sure, that you include the folder `src/Resources/app/storefront/dist` in your zip file. This is necessary because your Javascript files won't be compiled, when the plugin is activated in the administration.
