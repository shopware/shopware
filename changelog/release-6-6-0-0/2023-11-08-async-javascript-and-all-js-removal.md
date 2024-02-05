---
title: Async JavaScript and all.js removal
issue: NEXT-30176
---
# Storefront
* Deprecated `all.js` inside `Resources/views/storefront/layout/meta.html.twig`. Each app/plugin will get its own JavaScript served by a separate `<script>` tag instead.
* Deprecated webpack config inside `webpack.config.js`, will be replaced with config inside `webpack.multi-compiler.config.js`.
* Deprecated current contents of `Resources/app/storefront/src/main.js`, will be replaced with contents inside `Resources/app/storefront/src/main-async.js`.
___
# Upgrade Information

## Storefront async JavaScript and all.js removal

With the upcoming major version v6.6.0 we want to get rid of the `all.js` in the Storefront and also allow async JavaScript with dynamic imports.
Our current webpack compiling for JavaScript alongside the `all.js` does not consider asynchronous imports.

### New distribution of App/Plugin "dist" JavaScript

The merging of your App/Plugin JavaScript into an `all.js` will no longer take place. Each App/Plugin will get its own JavaScript served by a separate `<script>` tag instead.
Essentially, all JavaScript inside your "dist" folder (`ExampleApp/src/Resources/app/storefront/dist/storefront/js`) will be distributed into the `public/theme` directory as it is.
Each App/Plugin will get a separate subdirectory which matches the App/Plugin technical name as snake-case, for example `public/theme/<theme-hash>/js/example-app/`.

This subdirectory will be added automatically during `composer build:js:storefront`. Please remove outdated generated JS files from the old location from your "dist" folder.
Please also include all additional JS files which might have been generated due to dynamic imports in your release:

Before:
```
└── custom/apps/
    └── ExampleApp/src/Resources/app/storefront/dist/storefront/js/
        └── example-app.js
```

After:
```
└── custom/apps/
    └── ExampleApp/src/Resources/app/storefront/dist/storefront/js/
        ├── example-app.js         <-- OLD: Please remove
        └── example-app/           <-- NEW: Please include everything in this folder in the release
            ├── example-app.js     
            ├── async-example-1.js 
            └── async-example-2.js 
```

The distributed version in `/public/theme/<theme-hash>/js/` will look like below.

**Just to illustrate, you don't need to change anything manually here!**

Before:
```
└── public/theme/
    └── 6c7abe8363a0dfdd16929ca76c02aa35/
        ├── css/
        │   └── all.css
        └── js/
            └── all.js  
```

After:
```
└── public/theme/
    └── 6c7abe8363a0dfdd16929ca76c02aa35/
        ├── css/
        │   └── all.css
        └── js/
            ├── storefront/
            │   ├── storefront.js (main bundle of "storefront", generates <script>)
            │   ├── cross-selling_plugin.js
            │   └── listing_plugin.js
            └── example-app/
                ├── example-app (main bundle of "my-listing", generates <script>)
                ├── async-example-1.js
                └── async-example-2.js
```

### Re-compile your JavaScript

Because of the changes in the JavaScript compiling process and dynamic imports, it is not possible to have pre-compiled JavaScript (`ExampleApp/src/Resources/app/storefront/dist/storefront/js`)
to be cross-compatible with the current major lane v6.5.0 and v6.6.0 at the same time.

Therefore, we recommend to release a new App/Plugin version which is compatible with v6.6.0 onwards.
The JavaScript for the Storefront can be compiled as usual using the composer script `composer build:js:storefront`.

**The App/Plugin entry point for JS `main.js` and the general way to compile the JS remains the same!**

Re-compiling your App/Plugin is a good starting point to ensure compatibility.
If your App/Plugin mainly adds new JS-Plugins and does not override existing JS-Plugins, chances are that this is all you need to do in order to be compatible.

### Registering async JS-plugins (optional)

To prevent all JS-plugins from being present on every page, we will offer the possibility to fetch the JS-plugins on-demand.
This is done by the `PluginManager` which determines if the selector from `register()` is present in the current document. Only if this is the case the JS-plugin will be fetched.

The majority of the platform Storefront JS-plugin will be changed to async.

**The general API to register JS-plugin remains the same!**

If you pass an arrow function with a dynamic import instead of a normal import,
your JS-plugin will be async and also generate an additional `.js` file in your `/dist` folder.

Before:
```js
import ExamplePlugin from './plugins/example.plugin';

window.PluginManager.register('Example', ExamplePlugin, '[data-example]');
```
After:
```js
window.PluginManager.register('Example', () => import('./plugins/example.plugin'), '[data-example]');
```

The "After" example above will generate:
```
└── custom/apps/
    └── ExampleApp/src/Resources/app/storefront/dist/storefront/js/
        └── example-app/           
            ├── example-app.js                 <-- The main app JS-bundle
            └── src_plugins_example_plugin.js  <-- Auto generated by the dynamic import
```

### Override async JS-plugins

If a platform Storefront plugin is async, the override class needs to be async as well.

Before:
```js
import MyListingExtensionPlugin from './plugin-extensions/listing/my-listing-extension.plugin';

window.PluginManager.override(
    'Listing', 
    MyListingExtensionPlugin, 
    '[data-listing]'
);
```
After:
```js
window.PluginManager.override(
    'Listing', 
    () => import('./plugin-extensions/listing/my-listing-extension.plugin'),
    '[data-listing]',
);
```

### Async plugin initialization with `PluginManager.initializePlugins()`

The method `PluginManager.initializePlugins()` is now async and will return a Promise because it also downloads all async JS-plugins before their initialization.
If you need access to newly created JS-Plugin instances (for example after a dynamic DOM-update with new JS-Plugin selectors), you need to wait for the Promise to resolve.

Before:
```js
/**
 * Example scenario:
 * 1. A dynamic DOM update via JavaScript (e.g. Ajax) adds selector "[data-form-ajax-submit]"
 * 2. PluginManager.initializePlugins() intializes Plugin "FormAjaxSubmit" because a new selector is present.
 * 3. You need access to the Plugin instance of "FormAjaxSubmit" directly after PluginManager.initializePlugins().
 */
window.PluginManager.initializePlugins();

const FormAjaxSubmitInstance = window.PluginManager.getPluginInstanceFromElement(someElement, 'FormAjaxSubmit');
// ... does something with "FormAjaxSubmitInstance"
```

After:
```js
/**
 * Example scenario:
 * 1. A dynamic DOM update via JavaScript (e.g. Ajax) adds selector "[data-form-ajax-submit]"
 * 2. PluginManager.initializePlugins() intializes Plugin "FormAjaxSubmit" because a new selector is present.
 * 3. You need access to the Plugin instance of "FormAjaxSubmit" directly after PluginManager.initializePlugins().
 */
window.PluginManager.initializePlugins().then(() => {
    const FormAjaxSubmitInstance = window.PluginManager.getPluginInstanceFromElement(someElement, 'FormAjaxSubmit');
    // ... does something with "FormAjaxSubmitInstance"
});
```

If you don't need direct access to newly created JS-plugin instances via `getPluginInstanceFromElement()`, and you only want to "re-init" all JS-plugins, 
you do not need to wait for the Promise of `initializePlugins()` because `initializePlugins()` already downloads and initializes the JS-plugins. 

### Avoid import from PluginManager

Because the PluginManager is a singleton class which also assigns itself to the `window` object,
it should be avoided to import the PluginManager. It can lead to unintended side effects.

Use the existing `window.PluginManager` instead.

Before:
```js
import PluginManager from 'src/plugin-system/plugin.manager';

PluginManager.getPluginInstances('SomePluginName');
```
After:
```js
window.PluginManager.getPluginInstances('SomePluginName');
```

### Avoid import from Plugin base class

The import of the `Plugin` class can lead to code-duplication of the Plugin class in every App/Plugin.

Use `window.PluginBaseClass` instead.

Before:
```js
import Plugin from 'src/plugin-system/plugin.class';

export default class MyPlugin extends Plugin {
    // Plugin code...
};
```
After:
```js
export default class MyPlugin extends window.PluginBaseClass {
    // Plugin code...
};
```
