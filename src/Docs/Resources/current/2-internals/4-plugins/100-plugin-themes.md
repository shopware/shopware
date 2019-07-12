[titleEn]: <>(Storefront Themes)

This guide is about theme plugins which change the appearance or the behavior of the storefront.

Theme plugins will appear in the theme manger of the administration and can be 
selected and configured by the shop owner.


## Overview

If you want to create a custom theme to change the storefront, you have to create a `theme.json`
file in the Resources folder.

Basic example for `theme.json`:

```json
{
  "name": "Just another theme",
  "author": "Just another theme",
  "style": [
    "src/style/base.scss"
  ],
  "script": [
    "dist/js/app.js"
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
| script   | Array of paths to script files (e.g. javascript           |
| asset    | Array of paths to asset folders                           |

Please be aware that all paths have to be relative to the theme.json 
without leading and trailing slashes.

If you want to change the location of the `theme.json`, define the method `getThemeConfigPath`
in the plugin bootstrap file.

Example

```php
<?php declare(strict_types=1);

namespace Swag\PluginQuickStart;

use Shopware\Core\Framework\Plugin;

class PluginQuickStart extends Plugin
{
        public function getThemeConfigPath(): string
        {
            return 'Resources/theme.json';
        }
}
```

## Theme Configuration

One of the benifits of creating a theme is that you can overwrite the theme configuration of 
the default theme or add your own configuration.

Example `theme.json`
```json
{
  "config": {
    "sw-color-brand-primary": {
    "label": {
      "en-GB": "Primary",
      "de-DE": "Primär"
    },
    "type": "color",
    "value": "#399",
    "editable": true,
    "block": "colors",
    "section": "generalColors",
    "order": 100
    },
    "sw-font-family-headline": {
      "label": {
        "en-GB": "Headline",
        "de-DE": "Überschrift"
      },
      "type": "fontFamily",
      "value": "'Inter', sans-serif",
      "editable": true,
      "block": "fonts",
      "section": "generalFonts",
      "order": 200
    },
    "sw-logo-default": {
      "label": {
        "en-GB": "Default",
        "de-DE": "Standard"
      },
      "type": "media",
      "value": "dist/assets/logo/demostore-logo.png",
      "editable": true,
      "block": "media",
      "section": "logos",
      "order": 300
    }
  }
}
```

The example above shows how a theme configuration can look. The `theme.json` contains a
`config` property which contains a list of config items. The key of each config item is also
technical name which you use to access the config option in your theme or scss files.

The following parameters can be definined for a config item:

| Name         | Meaning                                                                              |
|------------- |--------------------------------------------------------------------------------------|
| label        | Array of translations with locale code as key                                        |
| type         | Type of the config. Possible values: color, fontFamily and media                     |
| value        | Value for the config                                                                 |
| editable     | If set to false, the config option will not be displayed (e.g. in the administration |
| block        | Name of a block to orginize the config options                                       |
| section      | Name of a section to orginize the config options                                     |
| block_order  | Numeric value to define the order of all elements with the same block                |
| section_order| Numeric value to define the order of all elements with the same section              |


If your plugin is installed and active, Shopware will automatically collect all your 
script and style files. All SASS files (*.scss) in the style folder are compiled to css.

## Commands

The theme system can be controlled via CLI with the following commands.

### Change a theme:

```bash
bin/console theme:change
```

Calling the `theme:change` command without any arguments, will enable the interactive mode.

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


### Compile a theme

Calling the `theme:compile` command will recompile all themes which 
are assigned to a sales channel.

## Theme refresh
Normally new themes are detected automatically but if you want to trigger this process
run the:

```bash
bin/console theme:refresh
```
command