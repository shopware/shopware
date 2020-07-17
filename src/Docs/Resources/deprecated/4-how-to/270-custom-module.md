[titleEn]: <>(Add new module to administration)
[metaDescriptionEn]: <>(When dealing with a whole lot of plugin configuration possibilites, you might want to create your plugin's custom module. How this can be done, is explained in short in this article.)
[hash]: <>(article:how_to_custom_module)

## Overview

Managing your plugin's configuration is mostly done using the [plugin configuration](./../2-internals/4-plugins/070-plugin-config.md).
Bigger plugins tend to have a lot of possible configurations and quite often they have to provide a listing to manage their custom
entities. This cannot be solved with the generated plugin configuration. In this case you have to create a custom module.
A custom module can be created with the following steps.

## Setup

This HowTo **does not** explain how to create a new plugin for Shopware 6.
Head over to our [Plugin quick start guide](./../2-internals/4-plugins/010-plugin-quick-start.md) to
learn creating a plugin at first.

## Injecting into the administration

The main entry point to extend the administration via plugin is the `main.js` file.
It has to be placed into a `<plugin root>/src/Resources/app/administration/src` directory in order to be found by Shopware 6.

## Creating a new module

Every module in the Shopware 6 core can be found in the `module` directory relative to the administration source directory.
We recommend to copy this structure, so everybody being used to Shopware 6 core code will automatically get the hang of it as well.
The path in your plugin would be: `<plugin root>/src/Resources/app/administration/src/module`

Inside of this `module` directory in the core code, you find a list of all available modules. Stick to that and
create a new directory for each module of your plugin. In this case it is just one, so create a new directory
`custom-module` in there.
By default, each module has its own `index.js`, which will be loaded when importing your module's directory. You also have to load the directory in your 
`main.js` in order for any changes to take effect.

```js
import './module/custom-module';
```

You don't have to mention the `index.js` in the import path, this is done automatically.
Your custom module's `index.js` will already be considered, so go ahead and open the `index.js`. The main logic happens in there.

### index.js

First of all, you have to register your module using the `ModuleFactory`, which is available throughout our third party wrapper. This `Module` provides a method `register`, which expects a name and a configuration for your module.

```js
Shopware.Module.register('custom-module', {
    // Configuration here
});
```

Here you have to provide some basic meta information about your module, such as a `name`, a `description` and a `title`. 
Also, the property `type` should have the value `plugin`. This can be used e.g. to deactivate all changes made to the Administration by plugins at once.

Additional to those basic meta information, each module comes with a custom color and a custom icon.
Those will be used in the whole module and all components related to it, even in the title of your browser's tab.
A list of all available icons can be found [here](https://component-library.shopware.com/icons/).

```js
import './page/custom-module-overview';

import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('custom-module', {
    type: 'plugin',
    name: 'Custom',
    title: 'Custom module',
    description: 'Description for your custom module',
    color: '#62ff80',
    icon: 'default-object-lab-flask',
});
```

You've registered your module, but neither does it have a navigation entry, nor does it have any routes
to open, once your navigation item is clicked.

### Snippets

First thing to do is registering new snippets. It is recommended to release plugins with snippets for multiple languages
included, to either provide snippet translations or at least make them possible. You may already have noticed the import
statements for `deDE` or `enGB` in the example above. These are your files, which only have to be registered as snippets
in order to work properly. This can be achieved by putting the following snippets right below the first block of information
and register those files to their designated locale code:

```js
snippets: {
    'de-DE': deDE,
    'en-GB': enGB
},
```


### Routes

In order to get the navigation working, every navigation entry needs an individual route to link to.

A module's routes are defined using the `routes` property, which expects an object containing multiple route configuration objects.
Each route is defined by its name, which is set using the configuration object's key, a component to link to and a path.

```js
// routes: {
//     nameOfTheRoute: {
//         component: 'example',
//         path: 'actualPathInTheBrowser'
//     }
// }
routes: {
    overview: {
        component: 'sw-product-list',
        path: 'overview'
    },
},
```

As previously mentioned, the key of the configuration object, `overview` in this case, is also the name of the route. This will be needed
for the navigation. `component` represents the name of the component to be shown when this route is executed.
Last but not least is the `path` property, which is the actual path for the route, therefore being used in the actual URL.
This configuration results in this route's full name being `custom.module.overview` and the URL being `/overview` relative to the
Administration's default URL.

In this case, the `sw-product-list` component is being used for this route. Usually you want to show your custom component here,
which is explained [here](./280-custom-component.md).

### Navigation

In order to create a navigation entry, you have to provide a navigation configuration using the `navigation` property in your module's configuration.
It expects an array of navigation configuration objects.

```js
import './page/custom-module-overview';

Shopware.Module.register('custom-module', {
    
    // Module configuration
    ...
    
    navigation: [{
        // Navigation configuration
    }]
});
```

It expects an array, so that you can provide more than just one navigation entry for your module.
Sometimes you want to have some kind of "parent" navigation entry, containing multiple children entries. In that scenario,
you also want the child entries to have a custom icon.
Thus, each navigation configuration can have its own `icon` and custom `color`. Other than those two properties,
you also need to provide a `label` to be displayed next to the icon. Additionally, add the name of a route by using the `path` property. This route will be used
when clicking on this navigation entry.
In this case, you use the previously created route `custom.module.overview`.

```js
navigation: [{
    label: 'Custom Module',
    color: '#62ff80',
    path: 'custom.module.overview',
    icon: 'default-object-lab-flask'
}]
```

### Final module

This is how your module should look like now:
```js
import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

Shopware.Module.register('custom-module', {
    type: 'plugin',
    name: 'Custom',
    title: 'Custom module',
    description: 'Description for your custom module',
    color: '#62ff80',
    icon: 'default-object-lab-flask',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        overview: {
            component: 'sw-product-list',
            path: 'overview'
        },
    },

    navigation: [{
        label: 'Custom Module',
        color: '#62ff80',
        path: 'custom.module.overview',
        icon: 'default-object-lab-flask'
    }]
});
```

## Loading the JS files

As mentioned above, Shopware 6 is looking for a `main.js` file in your plugin.
Its contents get minified into a new file named after your plugin and will be moved to the `public` directory
of the Shopware 6 root directory.
Given this plugin is named "CustomModule", the minified javascript code for this example would be
located under `<plugin root>/src/Resources/public/administration/js/custom-module.js`, once you run the command `./psh.phar administration:build` in your shopware root directory.
*Note: Your plugin has to be activated for this to work.*
Make sure to also include that file when publishing your plugin!
A copy of this file will then be put into the directory `<shopware root>/public/bundles/custommodule/administration/js/custom-module.js`.

The latter javascript file has to be injected into the template by your plugin as well for production environments.
In order to do this, create a new file called `index.html.twig` here: `<plugin root>/src/Resources/views/administration/`

```twig
{% sw_extends 'administration/index.html.twig' %}

{% block administration_scripts %}
    {{ parent() }}
    <script type="text/javascript" src="{{ asset('bundles/custommodule/administration/js/custom-module.js') }}"></script>
{% endblock %}
```

Your minified javascript file will now be loaded in production environments.

## Source

There's a GitHub repository available, containing this example source.
Check it out [here](https://github.com/shopware/swag-docs-custom-module).
