# Create a module
![Module Manifest](_old/img/module_manifest.jpg) to be replaced

Modules are the main elements of the administration. They're using the components and composing them to a full page for the administration interface. Modules are having a route to access them and usually at least one main menu entry to access them using the main menu. 

It is possible to easily create new modules for the administration by creating an `index.js` file which defines the important information of a module. Next to navigation entries, icons and a description you define new routes for the different views of a module. These routes are the entry points to the module and will render the corresponding page components. So the different views of a module are built by page components existing of other components from the core library or your own custom components.

## Module definition
The registration of a module is done by a JSON file. This file can be used for defining routes, menu entries, the modules colour and item or importing of components belonging to the module. Here's an example file structure for a module, based on the **link to getting started** guide and **link to component** guide.

### File structure
```
└── SwagAdministrationExample
    ├── Resources
    │   └── views
    │       └── administration
    │           ├── index.html.twig
    │           ├── main.js
    │           └── src
    │               ├── component
    │               │   └── swag-speech
    │               ├── extension
    │               └── module
    │                   └── swag-multimedia
    │                       ├── index.js
    │                       └── page
    │                           └── swag-multimedia-index
    │                               ├── index.js
    │                               └── swag-multimedia-index.html.twig
    └── SwagAdministrationExample.php

```
*File structure of module inside a plugin*

The component `swag-speech` was created during the **LINK TO COMPONENT GUIDE**  and will be reused for this example. The new example module is called `swag-multimedia`.

### Registering the module
The registration of modules is done with an `index.js` file describing the module.

```js
// swag-multimedia/index.js

import { Module } from 'src/core/shopware';

Module.register('swag-multimedia', {
    type: 'plugin',
    name: 'Multimedia module',
    description: 'This is a example module',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#d0e83a',
    icon: 'default-device-headset',

    routes: {
        // More later on
    },

    navigation: [
        // More later on
    ]
});
```

__module syntax:__

- the module name: 
  - The module name, in this example `swag-multimedia` is always separated in two parts. The first part is the developer prefix and the second part is the module name.
- `name`:
  - This is the name of the module without developer prefix.
- `type`:
  - The type of the module. Available types are `core` and `plugin` (default).
- `description`:
  - A short description of the module functionality in English. It is also possible to use a translation here.
- `version`:
  - A semver based version number for the module. More information about [semver can be found here](https://semver.org/).
- `targetVersion`: 
  - for future purpose.
- `color`:
  - We're having a colour-coded routing system in place, so that modules for a certain section getting a defined colour. Either a 3 or 6 character long colour hex code is valid.
- `icon`
  - Name of an icon from the shopware svg icon file, without the `icon--` prefix.
  
Make sure to import the module in `main.js`:
```js
// SwagAdministrationExample/Resources/views/administration/main.js

import './src/component/swag-speech';
import './src/module/swag-multimedia';
```

### Routing
For the administration we're using [Vue-Router](https://router.vuejs.org/en/) which is nicely integrated into Vue.js. Please keep in mind that your module isn't accessible in the administration interface when no route is defined. The module factory which registers the module will throw warnings in the developer console. 

```js
// swag-multimedia/index.js

import { Module } from 'src/core/shopware';

Module.register('swag-multimedia', {
    // ...

    routes: {
        index: {
            components: {
                default: 'swag-speech'
            },
            path: 'index'
        }
    }
});
```
*Example of registering a route*

The module will be available under the route  `http://example.shop/admin#/swag/multimedia/index` and renders the `swag-speech` component.

The [`vue-router` plugin](https://router.vuejs.org/en/) also supports route aliases:

```js
// swag-multimedia/index.js

import { Module } from 'src/core/shopware';

Module.register('swag-multimedia', {
    // ...

    routes: {
        index: {
            components: {
                default: 'swag-speech'
            },
            path: 'index',
            alias: 'list'
        }
    }
});
```
*Defining an alias for a route*

The following two routes will be generated:

- `http://example.shop/admin#/swag/multimedia/index`
- `http://example.shop/admin#/swag/multimedia/list`

For more information, head over to the [redirect documentation of the `vue-router` plugin](https://router.vuejs.org/en/essentials/redirect-and-alias.html).

### Navigation
To access the module using the main menu in the administration we have to register a main menu entry. The property `navigation` accepts an array of menu entries:

```js
// swag-multimedia/index.js

import { Module } from 'src/core/shopware';

Module.register('swag-multimedia', {
    // ...

    navigation: [{
        id: 'swag-multimedia-index',
        label: 'swag-multimedia',
        color: '#d0e83a',
        path: 'swag.multimedia.index',
        icon: 'default-device-headset'
    }]
});
```
*Registering a main menu entry*

The `path` property is important here because it relates to the routes configuration. The path has to be the named route of the routes configuration. The name is based on the module name and the routes key. Please keep in mind that the hyphen in the module name will be replaced with a dot during the initialization of the route.

Same as the module a navigation entry can have a color and an icon.

It is also possible to nest menu entries. Just use the `parent` property to define the parent named route:

```js
// swag-multimedia/index.js

import { Module } from 'src/core/shopware';

Module.register('swag-multimedia', {
    // ...

    navigation: [{
        label: 'swag-multimedia.index',
        color: '#d0e83a',
        path: 'swag.multimedia.index',
        icon: 'default-device-headset'
    },{
        label: 'swag-multimedia.create',
        color: '#d0e83a',
        path: 'swag.multimedia.create',
        icon: 'default-device-headset',
        parent: 'swag.multimedia.index'
    }]
});
```
*Nested navigation entries example*

### Pages
A module is normally based on a `sw-page`-component. This provides some basic features that are often used in the administration, like the integration of the search bar or some styling. Page components are nothing else than normal components. 

The first page of the module lives in an subfolder called `page`. Here is a example:
```
── swag-multimedia
   ├── index.js
   └── page
       └── swag-multimedia-index
           ├── index.js
           └── swag-multimedia-index.html.twig
```

This page is a very basic component and only registers a template for this example.
```js
// swag-multimedia/page/swag-multimedia-index/index.js

import { Component } from 'src/core/shopware';
import template from './swag-multimedia-index.html.twig';

Component.register('swag-multimedia-index', {
    template
});
```
*Example of index page component*

In the template file the `sw-page` component is used as the root element. The content of the component lives inside the `content` slot of `sw-page`. The `swag-speech` component is then inserted and reused.

```twig
// swag-multimedia/page/swag-multimedia-index/swag-multimedia-index.html.twig

<sw-page class="swag-multimedia-index">
    <swag-speech slot="content"></swag-speech>
</sw-page>
```
*Page component template example*

Back to the modules `index.js`, go to the route component definition and use your component `swag-multimedia-index`. Here is the complete file:

```js
// swag-multimedia/page/swag-multimedia-index/index.js

import { Module } from 'src/core/shopware';
import './page/swag-multimedia-index';

Module.register('swag-multimedia', {
    type: 'plugin',
    name: 'Multimedia module',
    description: 'This is a example module',
    version: '1.0.0',
    targetVersion: '1.0.0',
    color: '#d0e83a',
    icon: 'default-device-headset',

    routes: {
        index: {
            components: {
                default: 'swag-multimedia-index'
            },
            path: 'index'
        }
    },

    navigation: [{
        label: 'swag-multimedia',
        color: '#d0e83a',
        path: 'swag.multimedia.index',
        icon: 'default-device-headset'
    }]
});
```
*Using your page component*

### Download complete example
The complete example plugin can be downloaded ** LINK TO DOWNLOAD 'HERE' ** 