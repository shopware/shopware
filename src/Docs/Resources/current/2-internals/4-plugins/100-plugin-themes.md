[titleEn]: <>(Storefront Themes)

This guide is about theme plugins which change the appearance or the behavior of the storefront.

Theme plugins can be created like any other plugin 
(see the [Plugin Quickstart Guide](./010-plugin-quick-start.md), but will appear in the 
theme manger of the administration and can be selected and configured by the shop owner.

## Difference between "themes" and "regular" plugins
There are basically two ways to change the appearance of the storefront. You can have a "regular" plugins
which main purpose is to add new functions and change the behavior of the shop. 
These Plugins might also contain scss/css and javascript to be able to embed their new content into
the storefront correctly.
                                                                         
A shop manager can install your plugin over the plugin manager and your scripts and styles will 
automatically be embedded. The theme which is currently selected by the shop manager will be
recompiled with your custom styles.

The second way to change the appearance of the storefront is to create a theme. The main purpose of themes
is to change the appearance of the storefront and they behave a bit different compared to "regular" plugins.

Technically a theme is also a plugin but it will not only appear in the plugin manager of the administration,
it will also be visible in the theme manger once activated in the plugin manager.
To distinguish a theme plugin from a "regular" plugin you need to implement the Interface _Shopware\Storefront\Framework\ThemePlugin_ 
A theme can inherit from other themes, overwrite the default configuration (colors, fonts, media) and
add new configuration options.

This guide will focus on theme plugins. You can find a guide on how to create
"regular" storefront plugins [here](./090-plugin-storefront.md)


## Overview

You can create a fully functional theme skeleton by calling the command:

```bash
bin/console theme:create
```

Alternatively here is a step-by-step guide.

If you want to create a custom theme to change the storefront, you have to create a `theme.json`
file in the `<plugin root>/src/` folder of your plugin.

Basic example for `theme.json`:

```json
{
  "name": "Just another theme",
  "author": "Just another author",
  "description": {
    "en-GB": "Just another description",
    "de-DE": "Nur eine weitere Beschreibung"
  },
  "style": [
    "@Storefront",
    "Resources/style/base.scss"
  ],
  "script": [
    "@Storefront",
    "Resources/dist/js/plugin-name.js"
  ],
  "asset": [
    "dist/assets"
  ]
}
```

Common options:

| Name     | Meaning                                                   |
|----------|-----------------------------------------------------------|
| style    | Array of paths to style files (e.g. *.css/*.scss          |
| script   | Array of paths to compiled script files (e.g. javascript) |
| asset    | Array of paths to asset folders                           |

Using `@Storefront` in the `style` and `script` array specifies that your theme extends
the basic storefront theme which ships with shopware. This is useful if a theme just makes some
adjustments to this theme or to use it as a starting point.
Without these entries the storefront would be completely unstyled.

Please be aware that all paths have to be relative to the theme.json 
without leading and trailing slashes.

If you want to change the location of the `theme.json`, define the method `getThemeConfigPath`
in the plugin bootstrap file.

Example:

```php
<?php declare(strict_types=1);

namespace Swag\ThemeQuickStart;

use Shopware\Core\Framework\Plugin;
use Shopware\Storefront\Framework\ThemeInterface;

class ThemeQuickStart extends Plugin implements ThemeInterface
{
        public function getThemeConfigPath(): string
        {
            return 'theme.json';
        }
}
```

Once you have created a custom theme you need to install and activate it:
```bash
bin/console plugin:install --activate ThemeQuickStart
```

Next you need to run the `theme:refresh` command:

```bash
bin/console theme:refresh
```

This command checks all plugins and if a new theme is found, it will be registered.
Please be aware that this command currently doesn't recognize changes in the config, name or
author of the theme.

## Theme assets
You can add custom styles or javascript by using the `style` and `script` property in the `theme.json`.
Example `theme.json`
```json
{
  "name": "Just another theme",
  "author": "Just another author",
  "description": {
    "en-GB": "Just another description",
    "de-DE": "Nur eine weitere Beschreibung"
  },
  "style": [
    "@Storefront",
    "Resources/style/base.scss"
  ],
  "script": [
    "@Storefront",
    "Resources/dist/js/plugin-name.js"
  ]
}
```

In the example above, first of all we used the style `@Storefront`. Using `@{Bundle name}` will tell the
theme system to include all style of this bundle. Please be aware that the assets will be imported
in the same order as defined in the `theme.json`. So for the the example above, all styles of the
storefront will be included before your own.

For your own assets all files must be included by adding their path relative to the theme.json without
leading and trailing slashes. Style files can be `.css` and `.scss`. Script files can be `.js` files.

Style files will be compiled by PHP but not the .js files. So in most cases you do not enter the path of
your `.js` source but rather to the compiled file. The shopware webpack configuration will output the
compiled file under `<plugin root>/src/Resources/dist/storefront/js/plugin-name.js`. so you can add this 
path to your theme.json.

To trigger the build process of javascript, you can call 
```bash
bin/console storefront:build
``` 

This will compile the javascript and trigger a rebuild of the theme, so all your script and style changes
are be visible.

Please have a look at the [storefront assets](./../../4-how-to/330-storefront-assets.md) documentation
for more information about the build process and how to add your own assets. 
## Theme Configuration

One of the benefits of creating a theme is that you can overwrite the theme configuration of 
the default theme or add your own configuration.

Example `theme.json`
```json
{
  "name": "Just another theme",
  "author": "Just another author",
  "description": {
    "en-GB": "Just another description",
    "de-DE": "Nur eine weitere Beschreibung"
  },
  "style": [
    "@Storefront"
  ],
  "script": [
    "@Storefront"
  ],
  "config": {
    "fields": {
      "sw-color-brand-primary": {
        "value": "#00ff00"
      }
    }
  }
}
```

In the example above, we change the primary color to green. You always inherit from the storefront
config and both configurations are merged. This also means that you only have to provide the values you
actually want to change. You can find a more detailed explanation of the configuration inheritance
in the next section.

The `theme.json` contains a `config` property which consists a list of blocks, sections and fields.

The key of each config fields item is also the technical name which you use to access the config option
in your theme or scss files. `config` entries will show up in the administration and 
can be customized by the enduser (if `editable` is set to `true`, see table below).

The following parameters can be defined for a config field item:

| Name         | Meaning                                                                              |
|------------- |--------------------------------------------------------------------------------------|
| label        | Array of translations with locale code as key                                        |
| type         | Type of the config. Possible values: color, fontFamily and media                     |
| value        | Value for the config                                                                 |
| editable     | If set to false, the config option will not be displayed (e.g. in the administration |
| block        | Name of a block to organize the config options                                       |
| section      | Name of a section to organize the config options                                     |
| custom       | The defined data will not be processed but is available via API                      |


If your plugin is installed and active, Shopware will automatically collect all your 
script and style files. All SASS files (*.scss) in the style folder are compiled to css.

### Inheritance of theme config
All custom themes inherit the config of the Shopware default theme. The main reason is that in case 
of a new theme config due to a new shopware version, this option will also be available in your theme and
the storefront can rely on this configuration option.

So if you create a custom theme, the inheritance chain looks like this:

`Shopware default theme -> Your custom theme`

If you change any properties in your theme, they will override the shopware default theme.

When there are config fields with the `editable` property set to true, the user can change 
the values of these properties. So the chain would look like this:

`Shopware default theme -> Your custom theme -> Changes of the user`

If the user duplicates a theme in administration, the configuration is copied. This also
means that even if original theme changes, these change will not affect the duplicate.

## Blocks and sections
You can use blocks and sections to structure and group the config options.

![Example of blocks and sections](./img/theme-config.png)

In the picture above is one block "Colors" which contains two sections named "General colors" and
"Additional colors". You can define the block and section individually for each item. Example:
```json
{
  "name": "Just another theme",
  "author": "Just another author",
  
  "config": {
    "fields": {
      "sw-color-brand-primary": {
        "label": {
          "en-GB": "Primary",
          "de-DE": "Primär"
        },
        "type": "color",
        "value": "#399",
        "editable": true,
        "block": "colors",
        "section": "generalColors"
      }
    }
  }
}
```

The section property is not required.

You can extend the config to add translated labels for the blocks and sections:
```json
{
  "name": "Just another theme",
  "author": "Just another author",
  
  "config": {
    "blocks": {
      "colors": {
        "label": {
          "en-GB": "Colors",
          "de-DE": "Farben"
        }
      }
    },
    "sections": {
      "generalColors": {
        "label": {
          "en-GB": "General colors",
          "de-DE": "Allgemeine Farben"
        }
      }
    },
    "fields": {
      "sw-color-brand-primary": {
        "label": {
          "en-GB": "Primary",
          "de-DE": "Primär"
        },
        "type": "color",
        "value": "#399",
        "editable": true,
        "block": "colors",
        "section": "generalColors"
      }
    }
  }
}
```

## Commands

The theme system can be controlled via CLI with the following commands.

### Theme refresh
Normally new themes are detected automatically but if you want to trigger this process
run the command

```bash
bin/console theme:refresh
```

### Change a theme
After scanning for themes these can be activated using 
```bash
bin/console theme:change
```

Calling the `theme:change` command without any arguments will enable the interactive mode.

A list of all sales channels will be displayed:

```
Please select a sales channel:
  [0] Storefront | 138b4f705b174bf895366487df70cc22
  [1] Headless | 98432def39fc4624b33213a56b8c944d
```

Select one by entering the corresponding number (0, 1...). 
Afterwards you can choose a theme for the selected sales channel:

```
Please select a sales channel:
  [0] Storefront | 138b4f705b174bf895366487df70cc22
  [1] Headless | 98432def39fc4624b33213a56b8c944d
 > 0
Please select a theme:
  [0] Storefront
```

The selected theme will be assigned to the sales channel and compiled:
```
Please select a sales channel:
  [0] Storefront | 138b4f705b174bf895366487df70cc22
  [1] Headless | 98432def39fc4624b33213a56b8c944d
 > 0
Please select a theme:
  [0] Storefront
 > 0
Set "Storefront" as new theme for sales channel "Storefront"
Compile theme
```

The command can also be used non interactively:

```bash
# bin/console theme:change {themeName} {salesChannelId}
bin/console theme:change MyCustomTheme 138b4f705b174bf895366487df70cc22
```

If you want to assign your theme to all sales channels, use the --all option:
```bash
bin/console theme:change MyCustomTheme --all
```

`theme:change` will also compile the theme for all new assignments. 


### Compile a theme

Calling the `theme:compile` command will recompile all themes which 
are assigned to a sales channel.
