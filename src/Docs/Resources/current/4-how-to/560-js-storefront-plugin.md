[titleEn]: <>(Write your own storefront javascript plugin)
[metaDescriptionEn]: <>(This HowTo will give an example of writing an own javascript plugin for the storefront.)

## Overview

If you want to add interactivity to your storefront you probably have to write your own javascript plugin.
This HowTo will guide you through the process of writing and registering your own js plugins.
We will write an plugin that simply checks if the user has scrolled to the bottom of the page and then creates an alert.
For basic information on how to load own javascript or styles from plugins take a look at this [HowTo](./330-storefront-assets.md).

## Writing a js plugin

Storefront js plugins are vanilla javascript ES6 classes that extend from our Plugin base class.
For more background information on javascript classes take a look [here](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Classes).
To get started create a `Resources/storefront/example-plugin` folder and put an `example-plugin.plugin.js` file in there.
In that file create and export a ExamplePlugin class that extends the base Plugin class:

```js
import Plugin from 'src/script/plugin-system/plugin.class';

export default class ExamplePlugin extends Plugin {
}
```

Each Plugin has to implement the `init()` method. This method will be called when your plugin gets initialized and is the entrypoint to your custom logic.
In our case we add an callback to the onScroll event from the window and check if the user has scrolled to the bottom of the page. If so we display an alert.
Our full plugin now looks like this:

```js
import Plugin from 'src/script/plugin-system/plugin.class';

export default class ExamplePlugin extends Plugin {
    init() {
        window.onscroll = function() {
            if ((window.innerHeight + window.pageYOffset) >= document.body.offsetHeight) {
                alert('seems like there nothing more to see here.');
            }
        };
    }
}
```

## Registering your plugin

Next you have to tell shopware that your plugin should be loaded and executed. Therefore you have to register your plugin in the PluginManager.
Create a `main.js` file inside your `Resources/storefront` folder and get the PluginManager from the global window object. 
Then register your own plugin:

```js
// Import all necessary Storefront plugins and scss files
import ExamplePlugin from './example-plugin/example-plugin.plugin';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('ExamplePlugin', ExamplePlugin);
```

You also can register your plugin conditionally with an css selector. The best practice is to use data attributes that control if the plugin should be registered:

 ```js
 // Import all necessary Storefront plugins and scss files
 import ExamplePlugin from './example-plugin/example-plugin.plugin';
 
 // Register them via the existing PluginManager
 const PluginManager = window.PluginManager;
 PluginManager.register('ExamplePlugin', ExamplePlugin, '[data-scroll-detector]');
 ```

In this case the plugin just gets executed if the HTML document contains at least one element with the `data-scroll-detector` attribute.
But for our use case we register our plugin globally without any selector.

Lastly we have to ad a small code snippet for the HotModuleReload server to work with our custom plugins, so our full `main.js` file now looks like this:

```js
// Import all necessary Storefront plugins and scss files
import ExamplePlugin from './example-plugin/example-plugin.plugin';

// Register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('ExamplePlugin', ExamplePlugin);

// Necessary for the webpack hot module reloading server
if (module.hot) {
    module.hot.accept();
}
```

## Configuring your plugins script path

This can be ignored if you are on version 6.0.0 EA2 or newer. 

You have to tell shopware where your bundled .js files live, therefore you can implement the `getStorefrontScriptPath()` in your plugin base class.
By default shopware will bundle your javascript files and put them under `Resources/dist/storefront/js` during the build of the storefront.

```php
<?php declare(strict_types=1);

namespace Swag\JsPlugin;

use Shopware\Core\Framework\Plugin;

class JsPlugin extends Plugin
{
    public function getStorefrontScriptPath(): string
    {
        return 'Resources/dist/storefront/js';
    }
}
```

With version 6.0.0 EA2 the `Resources/dist/storefront/js` is the default path where shopware looks for your js files.

## Testing your changes

To see your changes you have to build the storefront. Use the `/psh.phar storefront:build` command and reload your storefront.
If you now scroll to the bottom of your page an alert should appear.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-js-plugin).


