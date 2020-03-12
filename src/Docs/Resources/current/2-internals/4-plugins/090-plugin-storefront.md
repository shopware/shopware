[titleEn]: <>(Storefront Plugins)
[hash]: <>(article:plugin_storefront)

This guide is about plugins which change the appearance or the behavior of the storefront.

## Difference between "themes" and "regular" plugins
There are basically two ways to change the appearance of the storefront. You can have a "regular" plugin
which main purpose is to add new functions and change the behavior of the shop. 
These Plugins might also contain scss/css and javascript to be able to embed their new content into
the storefront correctly.
                                                                         
A shop manager can install your plugin over the plugin manager and your scripts and styles will 
automatically be embedded. The theme which is currently selected by the shop manager will be
recompiled with your custom styles.

The second way to change the appearance of the storefront is to create a theme plugin. The main purpose of a theme
is to change the appearance of the storefront and they behave a bit different compared to "regular" plugins.

Technically a theme is also a plugin but it will not only appear in the plugin manager of the administration,
it will also be visible in the theme manger once activated in the plugin manager.
To distinguish a theme plugin from a "regular" plugin you need to implement the Interface _Shopware\Storefront\Framework\ThemeInterface_  
A theme can inherit from other themes, overwrite the default configuration (colors, fonts, media) and
add new configuration options.

This guide will focus on "regular" plugins. You can find a guide on how to create themes [here](./100-plugin-themes.md)

## Overview

If you want to add your own styles (css/scss) or Javascript to the Storefront, you can 
put them in the following folders:

Resources/storefront/script -> for script files like *.js
Resources/storefront/style -> for style files like *.css / *.scss

These are the defaults but its possible to change the path in your plugin bootstrap file.

The paths are relative to the plugin bootstrap file.

Example

```php
<?php declare(strict_types=1);

namespace Swag\PluginQuickStart;

use Shopware\Core\Framework\Plugin;

class PluginQuickStart extends Plugin
{
        public function getStorefrontScriptPath(): string
        {
            return 'another/path/script';
        }
    
        public function getStorefrontStylePath(): string
        {
            return 'another/path/style';
        }
}
```

If your plugin is installed and active, Shopware will automatically collect all your 
script and style files. All SASS files (*.scss) in the style folder are compiled to css.

They are also automatically embedded and loaded in the storefront.
