[titleEn]: <>(Modules)
[hash]: <>(article:developer_administration_modules)

To create a new module you have to create a new directory `<plugin root>/src/Resources/app/administration/src/module/swag-plugin`, so you can store your own modules files in there.
In this case we named our module `swag-plugin`. Right afterwards create a new file called `index.js` in there. This is necessary, because Shopware 6 is automatically requiring an `index.js` file
for each module. Consider it to be the main file for your custom module.

The only entry point of your plugin is the `main.js` file. To let Shopware 6 known about your module you have to load and register it.
Add the following `import` statement to the `main.js` file of your plugin.

```js
// src/Resources/app/administration/src/main.js

import './module/swag-plugin';
```

Now your module's `src/Resources/app/administration/src/swag-plugin/index.js` will be executed.

## Registering the module

When creating a new module you have to register it. This could be done by using the `Shopware.Module.register()` method.
The method needs two parameters to be set, the first one being the module's name, the second being a javascript object, which contains your module's configuration.

```js
Shopware.Module.register('swag-plugin', {
    // configuration here
});
```

## Configuring the module

The configuration contains a bunch of options, e.g. the color of your module. Each module asks for a color, which will be used automatically throughout your module.
In this example `#ff3d58` is used as a color, which is a soft red. Also, each module has a his own icon.
Which icons are available in Shopware 6 by default can be seen [here](https://component-library.shopware.com/#/icons/).
The plugin example uses the icon `default-shopping-paper-bag-product`, which will also be used for the module.

For displaying a actual browser title, just add a string for the key `title`. This will be the default title for your module, you can edit this for each component later on.

The last basic information you should set here, is the `description`, which will be shown as an empty-state.
What does that mean? The description will be shown for example, when you integrated a list component, but your list is empty as of now.
In that case, your module's description will be displayed instead.

Also very important are the routes, that your module is going to use, such as `swag-plugin-list` for the list of plugins and `swag-plugin-detail` for the detail page.
Those routes are configured as an object in a property named `routes`.

Let's have a look at the actual routes and how they have to look like:

```js
Shopware.Module.register('swag-plugin', {
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',
    title: 'Plugin',
    description: 'Manage plugin here.',
    
    routes: {
        list: {
            component: 'swag-plugin-list',
            path: 'list'
        },
        detail: {
            component: 'swag-plugin-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'swag.plugin.list'
            }
        }
    }
});
```

The `routes` uses the same api as the [vue router](https://router.vuejs.org).

The `detail` route comes with another new configuration, which is called `meta`. As the name suggests, you can use this object to apply
more meta information for your route. In this case the `parentPath` is filled. Its purpose is to link the path of the actual parent route.
In the administration, this results in a "back" button on the top left of your module when being on the detail page. This button will then link back to the list
route and the icon defined earlier will also be used for this button.

You might want to have a closer look at the `parentPath` value though. Its route follows this pattern:
`<plugin-name>.<name of the route>`

The `plugin-name` is separated by dots instead of dashes here though. The second part is the **name** of the route, the key of the route configuration that is.
Thus the path to the `list` route is `swag.plugin.list`.

## Setting up the menu entry

<p class="alert is--warning">
    Due to UX reasons, we're <b>not</b> supporting plugin modules to add new menu entries on the first level of the main menu. 
    Please use the "parent" property inside your navigation object to define the category where you want your menu entry
    will be appended to.<br>
    If you're planning to publish your plugin to the <a href="https://store.shopware.com/" title="Shopware Store" target="_blank">Shopware Store</a>
    keep in mind we're rejecting plugins which have created their own menu entry on the first level.
</p>

Let's define a menu entry using the `navigation` key in your module configuration. It takes an array of objects, each one configuring a route connected to your module.

In there you have to configure several things:

```js
navigation: [{
    label: 'plugin',
    color: '#ff3d58',
    path: 'swag.plugin.list',
    icon: 'default-shopping-paper-bag-product',
    position: 100,
    parent: 'sw-catalogue'
}]
```

## Additional meta info

You've got a menu entry now, which points to a `swag.plugin.list` route. The related routes are also setup already and linked to components, which will be
created in the next main step.
There's a few more configurations though that you should add to your module, such as a unique `name` and a `type`.

The `name` should be a technical unique one, the `type` would be 'plugin' here.
Why is that necessary? Imagine having a broken `Administration` after having installed 15 plugins. How do you figure out which one broke it now?
Right, you uninstall each plugin one by one and rebuild the administration each time until it works.
Wouldn't it be way cooler, if you could just disable all plugins (hence the `type`) from the administration for a second?
If you have a suspicion, wouldn't you want to just disable this special plugin from the administration, without actually disabling its full functionality?
The unique `name` would be required in that case, so just provide those two values as well.

And here's your final module:

```js
import './page/swag-plugin-list';
import './page/swag-plugin-detail';

Shopware.Module.register('swag-plugin', {
    type: 'plugin',
    name: 'plugin',
    title: 'swag-plugin.general.mainMenuItemGeneral',
    description: 'sw-property.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',

    routes: {
        list: {
            component: 'swag-plugin-list',
            path: 'list'
        },
        detail: {
            component: 'swag-plugin-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'swag.plugin.list'
            }
        }
    },

    navigation: [{
        label: 'swag-plugin.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'swag.plugin.list',
        icon: 'default-shopping-paper-bag-product',
        position: 100
    }]
});
```

## Link you module into settings 

Maybe want to link your module in the `settings` section of the administration. You can add the `settingsItem` option to
the module configuration like this.

```js
import './page/swag-plugin-list';
import './page/swag-plugin-detail';

Shopware.Module.register('swag-plugin', {
    ...

    settingsItem: {
        group: 'system',
        to: 'swag.plugin.list',
        icon: 'default-object-rocket'
    }
});
```

The `group` property targets to the group section the item will be display in ('shop', 'system').
The `to` gets the link path of the route. The `icon` contains the icon name which will be display. 
You can view the icon-set [here](https://component-library.shopware.com/icons/). 

