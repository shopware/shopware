[titleEn]: <>(Storefront Plugins)

This guide is about plugins which change the appearance or the behavior of the storefront.


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