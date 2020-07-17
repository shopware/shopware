[titleEn]: <>(Extend an existing JavaScript plugin)
[metaDescriptionEn]: <>(This HowTo will give an example of extending core JavaScript plugins and overriding the core logic.)
[hash]: <>(article:how_to_extend_js_plugin)

## Overview

 If you have to customize the logic of some core JavaScript storefront plugins you can override them with your own implementations.
 You will see how this works by extending the cookie permission plugin and showing the cookie notice on every page load and asking the user to confirm the accept action.
 Before continuing, make sure you know how JavaScript storefront plugins work by working through this [HowTo](./560-js-storefront-plugin.md).

## Extending an existing JavaScript plugin

As JavaScript storefront plugins are vanilla JavaScript classes you can simply extend them.
So in your case you create a `Resources/storefront/my-cookie-permission` folder and put an empty file `my-cookie-permission.plugin.js` in there.
Next you create a JavaScript class that extends the original CookiePermission plugin:

```js
import CookiePermissionPlugin from 'src/script/plugin/cookie-permission/cookie-permission.plugin';

export default class MyCookiePermission extends CookiePermissionPlugin {
}
```

Now you can override the functions from the parent class.
For now you first override the `init()` function to set the cookie value that is used to check if the user has accepted the cookie notice to an empty string (which will evaluate to false).
After that you call the init method of the original plugin.

```js
import CookiePermissionPlugin from 'src/script/plugin/cookie-permission/cookie-permission.plugin';
import CookieStorage from 'src/script/helper/storage/cookie-storage.helper';

export default class MyCookiePermission extends CookiePermissionPlugin {
    init() {
        CookieStorage.setItem('allowCookie', '');
        super.init();
    }
}
```

Lastly you want to prompt the user an confirm dialogue if he clicks the "Accept" button.
Therefore you override the `_hideCookieBar()` function to show the dialogue and just call the parent implementation if the user clicks "OK" in the confirm dialogue.
So your whole plugin now looks like this:

```js
import CookiePermissionPlugin from 'src/script/plugin/cookie-permission/cookie-permission.plugin';
import CookieStorage from 'src/script/helper/storage/cookie-storage.helper';

export default class MyCookiePermission extends CookiePermissionPlugin {
    init() {
        CookieStorage.setItem('allowCookie', '');
        super.init();
    }

    _hideCookieBar() {
        if (confirm('Are you sure?')) {
            super._hideCookieBar();
        }
    }
}
```

## Register your extended plugin

Next you have to register your extended plugin. You again use the PluginManager from the global window object in your `Resources/storefront/main.js` file for this.
But instead of using the `register()` function to register a new plugin, you use the `override()` function to indicate that you want to override an existing plugin.

```js
import MyCookiePermission from './my-cookie-permission/my-cookie-permission.plugin';

const PluginManager = window.PluginManager;
PluginManager.override('CookiePermission', MyCookiePermission, '[data-cookie-permission]');

// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}
```

## Configuring your plugins script path

This can be ignored if you are on version 6.0.0 EA2 or newer. 

You have to tell Shopware where your bundled .js files live, therefore you can implement the `getStorefrontScriptPath()` in your plugin base class.
By default Shopware will bundle your JavaScript files and put them under `Resources/dist/storefront/js` during the build of the storefront.

```php
<?php declare(strict_types=1);

namespace Swag\ExtendJsPlugin;

use Shopware\Core\Framework\Plugin;

class ExtendJsPlugin extends Plugin
{
    public function getStorefrontScriptPath(): string
    {
        return 'Resources/dist/storefront/js';
    }
}
```

## Testing your changes

To see your changes you have to build the storefront. Use the `/psh.phar storefront:build` command and reload your storefront.
You should see the cookie notice at the bottom of the page. If you click the "Accept" button you should be prompted to confirm your action.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-extend-js-plugin).


