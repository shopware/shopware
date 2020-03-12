[titleEn]: <>(Step 7: New module in the administration)
[hash]: <>(article:bundle_administration)

You've got a running custom entity with its very own database tables - but how does a shop manager actually create a bundle now?
Sure, you could just define your bundles in the database itself, but you know that's not a viable solution for the shop manager, he needs to be able
to configure his bundles in the administration.

Time to set up your very own bundle module consisting of a list, which displays all available bundles, as well as a detail page to edit
a single bundle or even create a new one.
The administration mainly uses [VueJS](https://vuejs.org/) as a framework. How to develop with VueJS is **not** explained here, head over to the [official documentation](https://vuejs.org/v2/guide/)
to learn more about the framework itself.

Of course any Shopware 6 specific code will be explained, don't worry about that.

## Setting up the the administration

Each plugin has a main entry point to add custom javascript code to the administration. By default, Shopware 6 is looking for a 
`main.js` file inside a `Resources/app/administration/src` directory in your plugin.
Thus, create a new file `main.js` in the directory `<plugin root>/src/Resources/app/administration/src`. That's it, this file will now be considered when building
the administration.

## Setting up a new module

You want to have your very own menu entry in the administration, which then should lead to a custom  bundle module.
In the `Administration` core code, each module is defined in a directory called `module`, so simply stick to it.
Inside of the `module` directory lies the list of several modules, each having their own directory named after the module itself. Makes sense, right?

So, go ahead and create a new directory `<plugin root>/src/Resources/app/administration/src/module/swag-bundle`, so you can store your own modules files in there.
Right afterwards create a new file called `index.js` in there. This is necessary, because Shopware 6 is automatically requiring an `index.js` file
for each module. Consider it to be the main file for your custom module.

Your custom module directory is not known to Shopware 6 yet, because why should it.
As mentioned earlier, the only entry point of your plugin is the `main.js` file. And that's the file you need to change now, so that it loads your new module.
For this, simply add the following line to your `main.js` file:
```js
import './module/swag-bundle';
```

Now your module's `index.js` will be executed.

### Registering the module

Your `index.js` is still empty now, so let's get going to actually create a new module.
This is technically done by calling the method `registerModule` method of our [ModuleFactory](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/core/factory/module.factory.js),
but you're not going to use this directly.

Instead, you're using the `Shopware.Module.register()` method, but why is that?

`Shopware` is a global object and it was created for third party developers. It is mainly the bridge between the Shopware Administration and our plugin.  
The `Module` object comes with a `register` helper method to easily register your module.
The method needs two parameters to be set, the first one being the module's name, the second being a javascript object, which contains your module's configuration.

```js
Shopware.Module.register('swag-bundle', {
    // configuration here
});
```

### Configuring the module

So, what do you configure here?
For example the color of your module. Each module asks for a color, which will be used automatically throughout your module.
In this example `#ff3d58` is used as a color, which is a soft red. Also, each module has a his own icon.
Which icons are available in Shopware 6 by default can be seen [here](https://component-library.shopware.com/#/icons/).
The bundle example uses the icon `default-shopping-paper-bag-product`, which will also be used for the module.
*Attention: This is not the icon being used for a menu entry!*

What about a title, which is used for the actual browser title?
Just add a string for the key `title`. This will be the default title for your module, you can edit this for each component later on.

The last basic information you should set here, is the `description`, which will be shown as an empty-state.
What does that mean? The description will be shown for example, when you integrated a list component, but your list is empty as of now.
In that case, your module's description will be displayed instead.

Also very important are the routes, that your module is going to use, such as `swag-bundle-list` for the list of bundles, `swag-bundle-detail` for the detail page and
`swag-bundle-create` for creating a new bundle entry.
Those routes are configured as an object in a property named `routes`.

### Setting up routes

Before continuing to explain how they are defined, let's have a look at the actual routes and how they have to look like:

```js
Shopware.Module.register('swag-bundle', {
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',
    title: 'Bundle',
    description: 'Manage bundles here.',
    
    routes: {
        list: {
            component: 'swag-bundle-list',
            path: 'list'
        },
        detail: {
            component: 'swag-bundle-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'swag.bundle.list'
            }
        },
        create: {
            component: 'swag-bundle-create',
            path: 'create',
            meta: {
                parentPath: 'swag.bundle.list'
            }
        },
    },
});
```

As mentioned already, the key for defining routes is `routes`. It has to be an object, where each key
represents the name for a new route. Thus `list` is the name of a new route from the `swag-bundle` module.
The respective value is the actual configuration of the route. A route points to a [component](https://vuejs.org/v2/guide/components.html) using the key `component`, which is the component to be shown when this
route is requested. The key `path` represents the actual path, that's going to be used for this route. Do not get confused just because it is equal to the
route name in the first route.

Have a look at this example route configuration:
```js
Shopware.Module.register('example', {
    routes: {
        exampleRoute: {
            component: 'my-custom-component',
            path: 'foo'
        }
    }
});
```

In this example, there's a new route with the name `exampleRoute`, which will open the `my-custom-component` component and is executed by browsing to the following link:
`https://example.shop/admin/#/example/foo`

The second route, `detail`, even comes with a dynamic parameter as part of the route. When you want to open a detail page of a bundle, the route also has to contain
the ID of the bundle.
Furthermore, the `detail` route comes with another new configuration, which is called `meta`. As the name suggests, you can use this object to apply
more meta information for your route. In this case the `parentPath` is filled. Its purpose is to link the path of the actual parent route.
In the administration, this results in a "back" button on the top left of your module when being on the detail page. This button will then link back to the list
route and the icon defined earlier will also be used for this button.

You might want to have a closer look at the `parentPath` value though. Its route follows this pattern:
`<bundle-name>.<name of the route>`

The `bundle-name` is separated by dots instead of dashes here though. The second part is the **name** of the route, the key of the route configuration that is.
Thus the path to the `list` route is `swag.bundle.list`.

The same applies for the `create` route, nothing special about it here.

There are several components linked with those routes, that do not exist yet. Don't worry, you didn't miss anything. Those are created later in this tutorial.

### Setting up the menu entry

Let's continue with the module configuration, what else is missing?
What about the menu entry, which opens your module in the first place? This is defined using the `navigation` key in your module configuration.
It takes an array of objects, each one configuring a route connected to your module.

But why can there be more than one navigation entry for a module?
Well, you could have a main entry which opens the bundle list, but there could be another menu entry, which would directly open the 'Create bundle' component.
You don't need that for this example though, simply create a single menu entry.

In there you have to configure several things:
<dl>
    <dt>label</dt>
    <dd>
        The label to be shown with this menu entry.
    </dd>
    
    <dt>color</dt>
    <dd>
        Yes, the color may differ from the module's color itself. It remains the same in this example though.
    </dd>
    
    <dt>path</dt>
    <dd>
        Which one of your configured routes shall be used when clicking this menu entry? Make sure to leave the path's name here.
    </dd>
    
    <dt>icon</dt>
    <dd>
        Also you can set a separate icon, which can make sense e.g. when having multiple menu entries for a single module, such as a special icon for 'Create bundle'.
        This example does not have this and it's only going to have a single menu entry, so use the icon from the main module here.
    </dd>
    
    <dt>position</dt>
    <dd>
        The position of the menu entry. The higher the value, the more likely it is that your menu entry appears in the bottom.
    </dd>
</dl>

Of course there's more to be configured here, but more's not necessary for this example.

```js
navigation: [{
    label: 'Bundle',
    color: '#ff3d58',
    path: 'swag.bundle.list',
    icon: 'default-shopping-paper-bag-product',
    position: 100
}]
```

### Additional meta info

You've got a menu entry now, which points to a `swag.bundle.list` route. The related routes are also setup already and linked to components, which will be
created in the next main step.
There's a few more configurations though that you should add to your module, such as a unique `name` and a `type`.

The `name` should be a technical unique one, the `type` would be 'plugin' here.
Why is that necessary?

Imagine having a broken `Administration` after having installed 15 plugins. How do you figure out which one broke it now?
Right, you uninstall each plugin one by one and rebuild the administration each time until it works.
Wouldn't it be way cooler, if you could just disable all plugins (hence the `type`) from the administration for a second?
If you have a suspicion, wouldn't you want to just disable this special plugin from the administration, without actually disabling its full functionality?
The unique `name` would be required in that case, so just provide those two values as well.

### Implementing snippets

You've already set a label for your module's menu entry. Yet, by default the `Administration` expects the value in there to be a [Vuei18n](https://kazupon.github.io/vue-i18n/started.html#html) variable, a translation key that is.
It's looking for a translation key `Bundle` now and since you did not provide any translations at all yet, it can't find any translation for it and will just print
the string itself. Sounds like it's time to implement translation snippets as well, right?

This is done by providing a new key to your module configuration, `snippets` this time.
It's basically an object, that contains another object for each language you want to support. In this example `de-DE` and of course `en-GB` will be supported.

Each language then receives a nested object of translations, so let's have a look at an example:
```json
{
    "swag-bundle": {
        "nested": {
            "value": "example"
        },
        "foo": "bar"
    }
}
```

In this example you would have access to two translations by the following paths: `swag-bundle.nested.value` to get the value 'example' and `swag-bundle.foo` to get the
value 'bar'. You can nest those objects as much as you want.

Since those translation objects become rather huge, you want to outsource them into separate files. For this purpose, create a new directory `snippet` in your module's directory
and in there two new files: `de-DE.json` and `en-GB.json`
*Note: In this example, only the `en-GB` file will be filled, in the actual [example source](https://github.com/shopware/swag-docs-bundle-example) we already provided the german translations.*

Each file could then contain your translations as such an object, you only have to import them into your module again.

```js
...

import deDE from './snippet/de-DE';
import enGB from './snippet/en-GB';

Shopware.Module.register('swag-bundle', {
    ...
    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },
});
```

Let's also create the first translation, which is for your menu's label.
It's key should be something like this: `swag-bundle.general.mainMenuItemGeneral`

Thus open the `snippet/en-GB.json` file and create the new object in there:
```json
{
    "swag-bundle": {
        "general": {
            "mainMenuItemGeneral": "Bundle"
        }
    }
}
```

Now use this path in your menu entry's `label` property:
```js
navigation: [{
    label: 'swag-bundle.general.mainMenuItemGeneral',
    color: '#ff3d58',
    ...
}]
```

There are more non-translated strings in your module, such as the `description` or the `title`, so just add those to your snippet file as well and edit the values of your module's
`description` and `title`.
The title will be the same as the main menu entry by default.

This should be your snippet file now:
```json
{
    "swag-bundle": {
        "general": {
            "mainMenuItemGeneral": "Bundle",
            "descriptionTextModule": "Manage bundles here"
        }
    }
}
```

### Final bundle module

And here's your final module:

```js
import './page/swag-bundle-list';
import './page/swag-bundle-detail';
import './page/swag-bundle-create';
import deDE from './snippet/de-DE';
import enGB from './snippet/en-GB';

Shopware.Module.register('swag-bundle', {
    type: 'plugin',
    name: 'Bundle',
    title: 'swag-bundle.general.mainMenuItemGeneral',
    description: 'sw-property.general.descriptionTextModule',
    color: '#ff3d58',
    icon: 'default-shopping-paper-bag-product',

    snippets: {
        'de-DE': deDE,
        'en-GB': enGB
    },

    routes: {
        list: {
            component: 'swag-bundle-list',
            path: 'list'
        },
        detail: {
            component: 'swag-bundle-detail',
            path: 'detail/:id',
            meta: {
                parentPath: 'swag.bundle.list'
            }
        },
        create: {
            component: 'swag-bundle-create',
            path: 'create',
            meta: {
                parentPath: 'swag.bundle.list'
            }
        }
    },

    navigation: [{
        label: 'swag-bundle.general.mainMenuItemGeneral',
        color: '#ff3d58',
        path: 'swag.bundle.list',
        icon: 'default-shopping-paper-bag-product',
        position: 100
    }]
});
```

The `page` imports in the first few lines will be created in the next few steps.

## The list component

Let's start with the main component for now: The list of bundles. You've already linked it in your module file and it's supposed to be named `swag-bundle-list` inside
a directory called `page`, so go ahead and create that directory.

Once again create a new file `index.js` in there as the main entry point for this component. To register a module, you used the code `Module.register()`,
now guess what you're going to use to register a new component.

```js
Shopware.Component.register('swag-bundle-list', {
    // Component configuration here
});
```

That was easy, right?

Do you remember what was said about each component being able to have their title? Therefore each page component has to define whether or not it wants to support
the default title being set in the module or if it want's a custom title to take place here.

This is defined by adding an `metaInfo` function, which returns the desired title. If you want to support the default one, call the default method `this.$createTitle()` instead.
And that's what will be done for this example.

```js
Shopware.Component.register('swag-bundle-list', {
    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },
});
```

### Setting a template

You want to create a listing now, which is done by using the `sw-entity-listing` element in a `twig` template, so let's start with creating a template for your
component.
Each component has a `template` property, which then contains the template. You want the template to be defined in a separate `.twig` file though,
so just create a new file named after the component in the component's directory.
Afterwards import your `swag-bundle-list.html.twig` file in your component and assign it to the template property.

```js
import template from './swag-bundle-list.html.twig';

Shopware.Component.register('swag-bundle-list', {
    template,
    
    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },
});
```

If you got confused by this syntax, an EcmaScript 6 feature is being used here. You can just pass in a variable, which is then both the key and the value.
Since you imported your template into the variable `template`, it perfectly fits with the object's property `template`.

For the sake of simplicity, you could also write it like that:
```js
import template from './swag-bundle-list.html.twig';

Shopware.Component.register('swag-bundle-list', {
    template: template,
    
    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },
});
```

Have a look [here](https://alligator.io/js/object-property-shorthand-es6/) for another example about it.

Let's print a simple 'Hello world' for your component now, so open up your twig template.
Each module's page should start with the `sw-page` component, so add this first:

```twig
{% block swag_bundle_list %}
    <sw-page class="swag-bundle-list">
        
    </sw-page>
{% endblock %}
```

Make sure to always use blocks in your component templates, if you want your plugin to be extendable itself.
The `sw-page` component automatically includes the search bar, a page header and the actual content.

In order to fill the content of a page, you have to use [VueJS slots](https://vuejs.org/v2/guide/components-slots.html) to override
the content of the `sw-page` component.

```twig
<template slot="content">
    <h2>Hello world!</h2>
</template>
```

### Theory: Slots vs Blocks

You might wonder now: Why didn't we use twig blocks for this case as well? When to use blocks, when to use the VueJs slots?
While this question can be a bit tricky, I'll try to explain it as simple as possible.

Generally speaking, you use the twig blocks when you are **extending** from another template and adjusting it to your needs.
Overriding a twig block would override it for all occurrences of this template.

You use the VueJs slots, when you're **using** an element instead. You use the `sw-page` element and you're technically just configuring
your single instance of it, not extending and changing the element as a whole.

### Setting up the listing

#### Listing template

You want to show a list of your bundle entities with the `swag-bundle-list` component.
Fortunately Shopware 6 comes with a neat component to be used for this specific case: `sw-entity-listing`

Basically, it only needs three attributes to be filled in order to have a running listing:
<dl>
    <dt>items</dt>
    <dd>
        Quite self-explaining. The items to be shown, the bundles in this case.
    </dd>
    
    <dt>repository</dt>
    <dd>
        The main repository you're using. Necessary for executing certain actions, such as `sorting`, `refreshing`, etc.
    </dd>
    
    <dt>columns</dt>
    <dd>
        Guess what, column configurations are expected here.
    </dd>
</dl>

This example uses a few more attributes though:
<dl>
    <dt>v-if</dt>
    <dd>
        Only showing the listing element when there's at least a single bundle available.
    </dd>
    
    <dt>showSelection</dt>
    <dd>
        Configures if the first column is a column showing 'selection' boxes. Defaults to true, but it's not necessary for this bundle example.
    </dd>
    
    <dt>detailRoute</dt>
    <dd>
        The name of the route to open when trying to open a detail page of an entry.
    </dd>
</dl>

So add the `sw-entity-listing` element like this:
```twig
<template slot="content">
    {% block swag_bundle_list_content %}
        <sw-entity-listing
            v-if="bundles"
            :items="bundles"
            :repository="repository"
            :showSelection="false"
            :columns="columns"
            detailRoute="swag.bundle.detail">
        </sw-entity-listing>
    {% endblock %}
</template>
```

Those attributes prefixed with a colon are a short-hand for [v-bind](https://vuejs.org/v2/api/#v-bind), so `:items` is the shorthand of `v-bind:items`.
Have a look at the documentation about `v-bind` linked above to understand what it does. In short: Each of those values are expressions, that can be filled dynamically.

#### Listing logic

In this example, all of those attributes will be processed in the `index.js` of your `swag-bundle-list` component.

So open up your component and let's start with filling the first necessary attribute, which is `bundles` as it is used in the `:items` attribute.

Your component has to load the bundles from our API and save the result into a variable named `bundles`, so your component template can then access those
bundles. This data should be available upon creation of your component. Fortunately, there's a lifecycle hook for this purpose, called [created](https://vuejs.org/v2/api/#created).
Having a look at the official documentation, you'll figure out, that it is defined using a function, so add this function to your component.

```js
Shopware.Component.register('swag-bundle-list', {
    
    ...
    
    created() {
        // add code to fill the variable 'bundles'
        this.bundles = bundles
    }
});
```

Now you wonder how to fill the `bundles` variable here. Do you still remember, that you created an `EntityDefinition` for your new table way earlier in this
tutorial? By this, you've registered your custom table to Shopware 6 [data abstraction layer](./../../2-internals/1-core/20-data-abstraction-layer/__categoryInfo.md) which then
also takes care of creating a repository for your bundles. This means, that you can access your bundles using the API.

Accessing the API in your component also works by fetching a repository and executing searches on it. Accessing the repository now opens up a whole new subject:
The administration also comes with a dependency injection container [bottleJs](https://github.com/young-steveo/bottlejs) is its name.
In the Shopware administration, there's a wrapper that makes `bottleJs` work with the [inject / provide](https://vuejs.org/v2/api/#provide-inject) from `VueJs`.
In short: You can use the `inject` key in your component configuration to fetch services from the `bottleJs` DI container, such as the `repositoryFactory`, 
that you will need in order to get your bundle repository.

Add those lines to your component configuration:
```js
inject: [
    'repositoryFactory'
],
```

This way the `repositoryFactory` becomes a local property that you can use. Talking about the repository, do you still remember which object you also needed for
the `sw-entity-listing`? Right, the `repository`, thus save it as an property to the component.

```js
{
    ...
    created() {
        this.repository = this.repositoryFactory.create('swag_bundle');
    }
}
```

Your repository provides a `search` method to actually request your repositories' data via API.
You need to provide two parameters to the search method, a `Criteria` object and the current `Shopware.Context.api`.
The `Criteria` object has to be instantiated by yourself, so you need to access it via the Shopware global object first.

```js
const Criteria = Shopware.Data.Criteria;
```

Time to run the search method of your repository, because you've got everything ready now. The `search` method will return a [promise](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise),
which will contain the search result upon resolving.
Here's the full `created` method:

```js
created() {
    this.repository = this.repositoryFactory.create('swag_bundle');

    this.repository
        .search(new Criteria(), Shopware.Context.api)
        .then((result) => {
            this.bundles = result;
        });
}
```

One more thing: You're setting properties like `repository` and `bundles` to your component on runtime, but your template is executed prior to that.
Thus, your template would usually break due to usage of unknown properties and an error would be thrown.
In order to prevent that, you can define an initial state of your component by using the [data](https://vuejs.org/v2/guide/instance.html#Data-and-Methods) method.

```js
data() {
    return {
        repository: null,
        bundles: null
    };
},
```

Just set null here, this is the initial state and will be updated once your `created` lifecycle hook is executed. Due to VueJs' two-way data binding,
all updates to those component properties being used in the template will automatically update in the template as well.


So you've taken care of loading the `bundles` property, which is used in your twig template, same as the `repository`. Only the `columns` are missing now, another
component property you need to set.

Let's talk about the structure of a column at first:
A column in the `sw-entity-listing` is basically defined as an object. Following will be the example `name` column, so we can talk about each
column property looking at the example.

```js
{
    property: 'name',
    dataIndex: 'name',
    label: this.$tc('swag-bundle.list.columnName'),
    routerLink: 'swag.bundle.detail',
    inlineEdit: 'string',
    allowResize: true,
    primary: true
}
```

<dl>
    <dt>property</dt>
    <dd>
        The actual source of data from the API. This column contains the value inside the `name` property in the result set.
    </dd>
    
    <dt>dataIndex</dt>
    <dd>
        Do not confuse with the dataIndex from ExtJS! This is used for **sorting** your column.
        If you are wondering, why you'd want to have the column sort due to another property than the one you're displaying:
        Imagine a value like "customerName", which consists of the customers firstname and the customers lastname. While the column shows the full name,
        you maybe only want to sort by the firstname or by the lastname. And what if you want to define how duplicated firstnames are sorted?
    </dd>
</dl>

An example here could look like this:
```js
{
    property: 'customer.name',
    dataIndex: 'customer.lastName, customer.firstName'
}
```

Here you would consider the lastname for sorting and only if a lastname is duplicated, the firstname will be considered.
There can and will be a difference between the value being shown and the one being used for sorting.

<dl>
    <dt>label</dt>
    <dd>
        Quite self-explaining, this is the label of the column. `this.$tc()` basically loads the given translation. This does not exist yet,
        but you should know by now where to add this translation.
    </dd>
    
    <dt>routerLink</dt>
    <dd>
        This is an optional property here. It's being used to make this name actually clickable and thus leading to the respective detail entry.
    </dd>
    
    <dt>inlineEdit</dt>
    <dd>
        When double clicking a row in the listing, you can edit it right away without opening the detail page. This property defines the type of the value,
        so in this case a text field will be shown.
    </dd>
    
    <dt>allowResize</dt>
    <dd>
        Just like this name suggests, the column can be resized if set to true.
    </dd>
    
    <dt>primary</dt>
    <dd>
        This just defines the primary column when initially opening the listing. This column will be used e.g. for initial sorting.
    </dd>
</dl>

And that's it. Those column definitions have to be inside the component property `columns`, because your template is looking for a property named
`columns`. Since you're using the translation service in the labels, you can't just put this property into the `data` method, because the translation
service might not have been set yet. Instead, use the `computed` method, have a look [here](https://vuejs.org/v2/guide/computed.html#Computed-Properties) to figure out the `computed` method's purpose.

In the same manner like the example above, go ahead and create each column like this.

I'll speed things up at this point, here's the full column definition for this listing:
```js
computed: {
    columns() {
        return [{
            property: 'name',
            dataIndex: 'name',
            label: this.$tc('swag-bundle.list.columnName'),
            routerLink: 'swag.bundle.detail',
            inlineEdit: 'string',
            allowResize: true,
            primary: true
        }, {
            property: 'discount',
            dataIndex: 'discount',
            label: this.$tc('swag-bundle.list.columnDiscount'),
            inlineEdit: 'number',
            allowResize: true
        }, {
            property: 'discountType',
            dataIndex: 'discountType',
            label: this.$tc('swag-bundle.list.columnDiscountType'),
            allowResize: true
        }];
    }
},
```

Note, that the `discountType` has no `inlineEdit` property. Hence, it can't be changed using inline edit.

You want to know what's the best about all this? You're done, at least with your listing. Try it, rebuild the administration using the following command
and then load your new Bundle module!
*Note: Since you didn't create the other two components `swag-bundle-detail` and `swag-bundle-create` **yet**, it will still fail to load your module.
Just remove those import lines from your module's `index.js` for testing purposes.

```bash
$ ./psh.phar administration:build
```

### Final list component

Your file should look like this:
```js
import template from './swag-bundle-list.html.twig';

const { Component } = Shopware;
const { Criteria } = Shopware.Data;

Component.register('swag-bundle-list', {
    template,

    inject: [
        'repositoryFactory'
    ],

    data() {
        return {
            repository: null,
            bundles: null
        };
    },

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    computed: {
        columns() {
            return [{
                property: 'name',
                dataIndex: 'name',
                label: this.$tc('swag-bundle.list.columnName'),
                routerLink: 'swag.bundle.detail',
                inlineEdit: 'string',
                allowResize: true,
                primary: true
            }, {
                property: 'discount',
                dataIndex: 'discount',
                label: this.$tc('swag-bundle.list.columnDiscount'),
                inlineEdit: 'number',
                allowResize: true
            }, {
                property: 'discountType',
                dataIndex: 'discountType',
                label: this.$tc('swag-bundle.list.columnDiscountType'),
                allowResize: true
            }];
        }
    },

    created() {
        this.repository = this.repositoryFactory.create('swag_bundle');

        this.repository
            .search(new Criteria(), Shopware.Context.api)
            .then((result) => {
                this.bundles = result;
            });
    }
});
```

## The detail component

Let's focus on the next component, the `swag-bundle-detail` component. This one is opened when somebody clicks either on the bundle name or on the `Edit` button of a
row. Since the process of creating a new component was explained in detail already, we'll speed this up a little bit here.

Create a new directory `swag-bundle-detail`, in there a new file `index.js` as entry point for your new component.
Register your component using `Component.register` and add a template in the configuration.
Also, set the default title again via `metaInfo` function.

```js
import template from './swag-bundle-detail.html.twig';

Shopware.Component.register('swag-bundle-detail', {
    template,
    
    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },
});
```

Also create the template file `swag-bundle-detail.html.twig` already, so we can focus on that at first.

### Detail template

Another page, so which component has to be used first in your template? The `sw-page` component, remember? In there you override
the 'content' template slot to fill the page's template.

```twig
<sw-page class="swag-bundle-detail">
    <template slot="content">
        
    </template>
</sw-page>
```

The detail pages in the Shopware administration are structured into several cards. Each card contains a defined set of fields.
This is done to structure a detail page into separate settings that can be grouped into those cards.
While your bundle is quite small and therefore does not require several cards, you still want to stick to the standard and use a card as well.

In order to use cards in your template, you have to use the `sw-card-view` component as a wrapper for every card. It contains the `sw-card` elements, 
in this example just a single one.

Each card can have his own title, but you don't need that here, there's only one card. Add the attribute `v-if` to your `sw-card` element though, since
the detail entry might still be loading and you only want it to show up once the bundle was successfully loaded:
```twig
<sw-page class="swag-bundle-detail">
    <template slot="content">
        <sw-card-view>
            <sw-card v-if="bundle">
                
            </sw-card>
        </sw-card-view>
    </template>
</sw-page>
```

The `v-if` directive also prevents errors when dealing with the detail data later, so make sure to have this set. As you might have noticed, it asks for a `bundle`
property to be set, so you gotta take care of that property in the respective component in a second.
But let's continue with the template for now, what would you want to show on a detail page? Probably some fields to edit the values, right?
That's what's going to happen next.

### Filling the card

For this purpose, we've created a component called `sw-field`, which is quite easy to configure.
Each field has a label, no need to explain that, a `v-model`, which points to the property name to be displayed in this field and, if necessary, the field's type, e.g. 'number'.

The first value you want to edit is a bundle's name. This should be a string field and this data is stored in the `name` property:
```twig
<sw-field :label="$t('swag-bundle.detail.nameLabel')" v-model="bundle.name" validation="required"></sw-field>
```

First of all, note the translation in the label, you should know how to add this translation now, right?
The `v-model` points to the `id` property of the `bundle`. This is not only used for reading, but also the value to be written when saving your changes.
That was easy, but this was just a string field.

The next field could be the `discount` value itself. This time, you're not dealing with a string, but a number / float.
Both the `label` and the `v-model` attributes are to be used like in the previous field, but this time you should also add the field's type:
```twig
<sw-field :label="$t('swag-bundle.detail.discountLabel')" v-model="bundle.discount" type="number"></sw-field>
```

This field is quite straight forward, it has a `label`, `v-model` and its `type` is `number`. This doesn't change the look of the field,
but comes with some neat features. For example you can only save numbers in there. While you can enter any alphanumeric character to this field,
those characters will be removed once you lose focus on this field.
Also, there's a few more attributes you could configure for a number field, if necessary, such as `:digits`, `:step` and `:min` or `:max`.

Now things become a bit more interesting. The `discountType` field only supports `percentage` and `absolute`,  so you should offer those values by providing radio buttons.
You need the same default attributes, `label`, `v-model` and `type` that is. The latter is of the type `radio` and as you might know, a radio button
always needs some options to display. That's also the last property you'll need: `:options`

```twig
<sw-field type="radio" :label="$t('swag-bundle.detail.discountTypeLabel')" v-model="bundle.discountType" :options="options"></sw-field>
```
The `options` will be defined in your `swag-bundle-detail` component.

There's only one more field missing right now. With those fields, you will be able to handle all the bundle's basic data. But how do you assign products?
For this purpose, Shopware 6 another neat component to handle this situation easily: `sw-entity-many-to-many-select`

Here's the code, before we have a look at what it does:
```twig
<sw-entity-many-to-many-select
    :label="$t('swag-bundle.detail.assignProductsLabel')"
    :localMode="bundle.isNew()"
    :entityCollection="bundle.products">
</sw-entity-many-to-many-select>
```

This component would render a field into your card, which loads all available products upon clicking the field. You can then click on a product to assign
it to your bundle, this assignment will be saved **immediately** to your bundle when you're on the detail page. The selected products are also displayed in boxes
and can easily be removed again. This field automatically provides a search function as well, so just start typing to enter a search string.

Now have a look at the field in the code and its attributes.
The simplest one is the `label` again, just like in every other field.
The next one is rather interesting though: `localMode`
As mentioned previously, the `sw-entity-many-to-many-select` component saves the clicked associated entities, products in this case, **immediately** after clicking them.
But what if you were to create a new bundle, that does not exist in the database yet? Which `bundle_id` would he save to the association?
That's where the `localMode` comes into place, which defines if the values should be saved upon clicking on them. If `localMode` is set to `true`, this association
will only be saved when actually saving the whole entity.
Since your `swag-bundle-create` component is going to extend from `swag-bundle-detail`, you can ensure this works for both cases by checking if the bundle is new.
This method, `bundle.isNew()`, is automatically available with each entity in the Administration.

The `:entityCollection` attribute is just like `v-model`, you simply point to the association's property name here.

### Adding action buttons

Your card contains all necessary fields now, but there's no save or cancel button yet.
You always want your custom module and components to look like it was from the official Shopware 6 Administration itself,
so your customers won't be having any issues understanding how to deal with the new modules and components.

By default, the save and the cancel button should always be part of the smart bar, which is the bar right above your module. 
This smart bar also provides a template slot, so you can add your buttons there.

```twig
<template slot="smart-bar-actions">
    {# Smart bar buttons here #}
</template>
```

Of course, the Administration also provides a default component for buttons. Start with the cancel button, because that's also
the default for all other modules. A button in the Administration can be implemented by using the `sw-button` component.

```twig
<template slot="smart-bar-actions">
    <sw-button :routerLink="{ name: 'swag.bundle.list' }">
        {{ $t('swag-bundle.detail.cancelButtonText') }}
    </sw-button>
</template>
```

There's just one thing to be explained here, which is the `:routerLink` property. All it does, is linking to the route you provided.
Thus, the cancel button will just bring you back to the `swag-bundle-list` page. Also, notice the translation again.

That's it already, now create another `sw-button` for saving your bundle and create a new translation for it.
The save button is a special kind of button, because it obviously does a bit more than linking to another route.
Additionally, you might want your save button to show any feedback, if the save was successful.
For this purpose, there's a component called `sw-button-process`, which will do just that.
Once the save is finished and was successful, the button will turn into a tick to show that feedback to the user as well.

You want your save button to stand out more than the cancel button, which you can do by adding the `variant` attribute and setting it to `primary`.
This will change the button's colors to be flashy and thus looking like the main action to be taken.

```twig
<sw-button-process variant="primary">
     {{ $t('swag-bundle.detail.saveButtonText') }}
 </sw-button-process>
```
Once you click the button, you want some code to be executed in order to actually save your changes. This code will be part of your `swag-bundle-detail` component
configuration. This will be done using the `@click` attribute, which is a shorthand for [v-on:click](https://vuejs.org/v2/guide/syntax.html#v-on-Shorthand).

```twig
<sw-button-process variant="primary" @click="onClickSave">
     {{ $t('swag-bundle.detail.saveButtonText') }}
 </sw-button-process>
```

The `sw-button-process` needs a few more information though in order to be fully functional.

```twig
<sw-button-process
    :isLoading="isLoading"
    :processSuccess="processSuccess"
    variant="primary"
    @process-finish="saveFinish"
    @click="onClickSave">
    {{ $t('swag-bundle.detail.saveButtonText') }}
</sw-button-process>
```

The `:isLoading` directive is necessary to show a loading indicator if the save process is still loading. Once `:processSuccess` is set to `true`, the tick will be shown.
Its value `processSuccess` is a property you need to set in your component in the next step, don't mind it for now.
Finally there's the `@process-finish` event being used. You need that in order to update the `processSuccess` property upon the save process being fully finished.

### Final detail template

Quite much text for this template, here's the full example and how it should look like now:
```twig
<sw-page class="swag-bundle-detail">
    <template slot="smart-bar-actions">
        <sw-button :routerLink="{ name: 'swag.bundle.list' }">
            {{ $t('swag-bundle.detail.cancelButtonText') }}
        </sw-button>

        <sw-button-process
            :isLoading="isLoading"
            :processSuccess="processSuccess"
            variant="primary"
            @process-finish="saveFinish"
            @click="onClickSave">
            {{ $t('swag-bundle.detail.saveButtonText') }}
        </sw-button-process>
    </template>

    <template slot="content">
        <sw-card-view>
            <sw-card v-if="bundle" :isLoading="isLoading">
                <sw-field :label="$t('swag-bundle.detail.nameLabel')" v-model="bundle.name"></sw-field>
                <sw-field :label="$t('swag-bundle.detail.discountLabel')" v-model="bundle.discount" type="number"></sw-field>

                <sw-field type="radio"
                      :label="$t('swag-bundle.detail.discountTypeLabel')"
                      v-model="bundle.discountType"
                      :options="options">
                </sw-field>

                <sw-entity-many-to-many-select
                    :localMode="bundle.isNew()"
                    :label="$t('swag-bundle.detail.assignProductsLabel')"
                    :entityCollection="bundle.products">
                </sw-entity-many-to-many-select>
            </sw-card>
        </sw-card-view>
    </template>
</sw-page>
```

### Detail logic

That's it for your `swag-bundle-detail` component's template, time to handle the logic necessary to make that template working.
This is the list of things you need to take care of now, due to your template:

<dl>
    <dt>bundle</dt>
    <dd>
        This property contains the detail bundle. It has to be loaded right after your component is created.
    </dd>
    
    <dt>onClickSave</dt>
    <dd>
        This method is triggered once the user clicks the save button. Has to trigger the actual save using the bundle's repository.
    </dd>
    
    <dt>isLoading</dt>
    <dd>
        This property is being used by your `sw-button-process` button. Needs to be updated once the save process is done.
    </dd>
    
    <dt>processSuccess</dt>
    <dd>
        This property is also used by the `sw-button-process` button to show the tick. Needs to be updated once the save process is done and after a period of time.
        While this property is set to `true`, the tick will be shown. You don't want that tick to show forever though, so this also needs to be updated after a period of time.
    </dd>
    
    <dt>saveFinish</dt>
    <dd>
        This method is fired once the `process-finish` event gets emitted. In fact, the respective event is emitted after a given timeout, which can be set using
        the `animationTimeout` attribute in your usage of the `sw-button-process` component. This method only has to reset the property `processSuccess` back to false, so the tick
        disappears again.
    </dd>
    
    <dt>options</dt>
    <dd>
        Property, which has to contain the options for your radio buttons.
    </dd>
</dl>

#### Loading the detail information

Let's work through this step by step, starting with loading the `bundle` for the detail page.
The `bundle` property has to contain the bundle data for the current detail page. This has to be loaded very early.
Do you still remember how you loaded the data for your `swag-bundle-list` component?
In short: You used `created` lifecycle hook of your component and injected the `repositoryFactory` in order to get the repository for your bundle.
The repository then executed the `search` method to fetch **all** bundles, but you only need a single one entity this time. This is done by using the `get` method instead,
which only needs the entity's ID and the `Shopware.Context.api`.
The ID can be retrieved from the route, which is available in your component like this: `this.$route.params.id`
Once the `get` method is executed, it will return a [promise](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise), which
then contains the result upon being resolved.
Also remember to have the `bundle` property set in the `data` method already, it's null in there.

Quite a lot of text, here's the code:
```js
import template from './swag-bundle-detail.html.twig';

Shopware.Component.register('swag-bundle-detail', {
    template,

    inject: [
        'repositoryFactory'
    ],
    
    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },
    
    data() {
        return {
            bundle: null
        };
    },
    
    created() {
        this.repository = this.repositoryFactory.create('swag_bundle');
        this.getBundle();
    },
    
    methods: {
        getBundle() {
            this.repository
                .get(this.$route.params.id, Shopware.Context.api)
                .then((entity) => {
                     this.bundle = entity;
                });
            },
        }
});
```

If you were wondering why the method `getBundle` exists, that's due to the fact that you'll need the same code in the next step again.
Therefore the `repository` is saved as a property to the component, otherwise it wouldn't be available in the `getBundle` method, right?

#### Saving the data

The method `onClickSave` is executed once the user clicks the button and has to save the bundle using the repository again.
That's where you can use the repository's `save` method, which asks for the entity itself and, again, the `Shopware.Context.api`. As always,
this method will return a promise upon which you can react. This time, a small error handling will be added here as well.
You may be wondering why there is error handling when saving, but not when reading. When reading data, you're just executing this search via default
code, most times there's no user input involved. Saving data though requires somebody to enter data, which may or may not be valid, thus the shop manager
has to be notified about him having messed up.
If you were to offer some kind of "filtering" due to user input, you might want to consider adding error handling to the reading of data as well.

Let's have a look at the successful case first though.
```js
Shopware.Component.register('swag-bundle-detail', {
    ...
    
    methods: {
        ...
        
        onClickSave() {
            this.repository
                .save(this.bundle, Shopware.Context.api)
                .then(() => {
                    this.getBundle();
                });
        },
    }
});
```

Once the save is finished, you want to fetch the saved bundle again, because there's a tiny chance it got edited by someone else at the same time.

That's it for the save process already. Let's add `isLoading` here real quick as well.

```js
onClickSave() {
    this.isLoading = true;
    
    this.repository
        .save(this.bundle, Shopware.Context.api)
        .then(() => {
            this.getBundle();
            this.isLoading = false;
        });
}
```

Set it to true right before sending the saved data and reset it to false, once the request is done.

As mentioned earlier, you will deal with errors in this case as well, since the shop manager might have entered invalid data to be saved.
You're doing this by adding a [catch](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/Promise/catch) call to the promise,
which only triggers once an error occurred. In this case, you want to show a message to the user by using the notification system.
In order to show a notification to the user, you have to add the `notification` mixin to your component.

First of all you have to access the `Mixin` object from the `Shopware` global object. This way you could simply call it with `Shopware.Mixin` just like `Shopware.Component`.
But instead of writing this every time, you could also use [object destructuring](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Operators/Destructuring_assignment#Basic_assignment) and assign a `Component` and a `Mixin` variable at the same time.

```js
const { Component, Mixin } = Shopware;
```

This can be very handy everytime you need to access multiple objects from `Shopware`.

This [example documentation](https://vuejs.org/v2/guide/mixins.html) from VueJS describes quite good, what a `mixin` even is, in case you were wondering.
The mixin comes with a method called `createNotificationError`, which you should use here. Its parameter has to be the object containing the config for the notification.
You only need to provide a `title` and a `message` in this object. Also, make sure to set the property `isLoading` to false here again to remove the loading indicator
if an error occurred.

```js
onClickSave() {
    this.isLoading = true;

    this.repository
        .save(this.bundle, Shopware.Context.api)
        .then(() => {
            this.getBundle();
            this.isLoading = false;
        }).catch((exception) => {
            this.isLoading = false;
            this.createNotificationError({
                title: this.$tc('swag-bundle.detail.errorTitle'),
                message: exception
            });
        });
},
```

That's it for the saving process.

#### Handling state for process button

You've added a `sw-button-process` which asks for two things to be set in order to work properly.
First of all, there's the `processSuccess` property, that you need to set to true upon a successful save. For this, just add the property
to your `data` method, so it's null on initiation and won't throw an error. Then set it to `true` once saving the bundle was successful.

```js
onClickSave() {
    ...

    this.repository
        .save(this.bundle, Shopware.Context.api)
        .then(() => {
            ...
            
            this.processSuccess = true
        })
},
```

The second thing you need to take care of, is the method `saveFinish`. It is executed by the [watch](https://github.com/shopware/platform/blob/master/src/Administration/Resources/app/administration/src/app/component/base/sw-button-process/index.js#L45) option,
which reacts on changes on the `processSuccess` property. Once a change happens to the `processSuccess` property, a timeout is applied and the event `process-finish` emitted once the timeout ran off.
You're calling the `saveFinish` method in your template then, which is supposed to reset the `processSuccess` property to false, so the `sw-button-process` resets its state as well.

```js
methods: {
    ...
    
    saveFinish() {
        this.processSuccess = false;
    },
}
```

#### Adding options for the multi select

You're almost done with the detail component! Just the `options` for the `sw-entity-many-to-many-select` are still missing.
But, what's the best time to set the options? When using the `data` method, you might not have access to the translation variable `this.$t` yet.
Thus, you could use the `created` method for that purpose, yet this method is only executed once after creating. What happens then, if you switch
the language in the Administration, so the options translation also has to change?
That's where the [computed properties](https://vuejs.org/v2/guide/computed.html#Computed-Properties) come in handy. Simply add the options
as a computed property in your component.

```js
computed: {
    options() {
        return [
            { value: 'absolute', name: this.$tc('swag-bundle.detail.absoluteText') },
            { value: 'percentage', name: this.$tc('swag-bundle.detail.percentageText') }
        ];
    }
},
```

### Final detail component

And that's it for your detail component! You've set a template for it, you took care of both reading and saving the data, you took care of translations,
the user gets notified about issues saving his data and even the save button shows the success state of the save.
Even the product association is very easy to handle now for your shop manager. 

Here's the full example of your detail component:
```js
import template from './swag-bundle-detail.html.twig';

const { Component, Mixin } = Shopware;

Component.register('swag-bundle-detail', {
    template,

    inject: [
        'repositoryFactory'
    ],

    mixins: [
        Mixin.getByName('notification')
    ],

    metaInfo() {
        return {
            title: this.$createTitle()
        };
    },

    data() {
        return {
            bundle: null,
            isLoading: false,
            processSuccess: false,
            repository: null
        };
    },

    computed: {
        options() {
            return [
                { value: 'absolute', name: this.$tc('swag-bundle.detail.absoluteText') },
                { value: 'percentage', name: this.$tc('swag-bundle.detail.percentageText') }
            ];
        }
    },

    created() {
        this.repository = this.repositoryFactory.create('swag_bundle');
        this.getBundle();
    },

    methods: {
        getBundle() {
            this.repository
                .get(this.$route.params.id, Shopware.Context.api)
                .then((entity) => {
                    this.bundle = entity;
                });
        },

        onClickSave() {
            this.isLoading = true;

            this.repository
                .save(this.bundle, Shopware.Context.api)
                .then(() => {
                    this.getBundle();
                    this.isLoading = false;
                    this.processSuccess = true;
                }).catch((exception) => {
                    this.isLoading = false;
                    this.createNotificationError({
                        title: this.$tc('swag-bundle.detail.errorTitle'),
                        message: exception
                    });
                });
        },

        saveFinish() {
            this.processSuccess = false;
        },
    }
});
```

## The create component

The component `swag-bundle-create` is rather easy, since you can extend from your detail component here. You're supposed show the very same form,
using the very same fields with the same configuration. There's just two things to be done in this case.
First, you have to create a new entity when the `getBundle` method is executed, you don't have to search for one.
Next, once you clicked the `save` button, you want to be redirected to the detail component, because now you're dealing with an existent bundle entry.

Go ahead and create your new component first, hence a `swag-bundle-create` directory inside your `page` directory, and then a new `index.js` file.

You're extending from a component by using the `extend` method of the `Component` class. The first parameter is the name of the new component,
the second parameter the name of the component to extend from, the third paramter the configuration again.

```js
Shopware.Component.extend('swag-bundle-create', 'swag-bundle-detail', {
    
});
```

Now go ahead and implement your own `getBundle` method. While the original `getBundle` method had to fetch an actual bundle entity by its ID,
you only have to create a new entity in this case. This does not send any request to the API, so also no need for a promise object here.
You create a new entity using the `create` method of a repository. It does neither require an `Criteria` instance, nor an ID to fetch any bundle.
It only needs the `Shopware.Context.api` to create a new entity.

```js
Shopware.Component.extend('swag-bundle-create', 'swag-bundle-detail', {
    methods: {
        getBundle() {
            this.bundle = this.repository.create(Shopware.Context.api);
        }
    }
});
```

That's it for the entity already.
Now you want to override the `onClickSave` method, so it actually redirects you to the detail component after successfully saving.
Simply copy the original method and add a redirect to the detail route, using your new entities' ID.
This is done by using the method `push` method on the router, which in turn is accessible via `this.$router`.

```js
onClickSave() {
    this.isLoading = true;

    this.repository
        .save(this.bundle, Shopware.Context.api)
        .then(() => {
            this.isLoading = false;
            this.$router.push({ name: 'swag.bundle.detail', params: { id: this.bundle.id } });
        }).catch((exception) => {
            this.isLoading = false;

            this.createNotificationError({
                title: this.$tc('swag-bundle.detail.errorTitle'),
                message: exception
            });
        });
}
```

That's it,you're done already! No need to set a custom template here or anything else, the `swag-bundle-create` component is already fully working.

### Final create component

That's how your `swag-bundle-create` should look like now:
```js

Shopware.Component.extend('swag-bundle-create', 'swag-bundle-detail', {
    methods: {
        getBundle() {
            this.bundle = this.repository.create(Shopware.Context.api);
        },

        onClickSave() {
            this.isLoading = true;

            this.repository
                .save(this.bundle, Shopware.Context.api)
                .then(() => {
                    this.isLoading = false;
                    this.$router.push({ name: 'swag.bundle.detail', params: { id: this.bundle.id } });
                }).catch((exception) => {
                    this.isLoading = false;

                    this.createNotificationError({
                        title: this.$tc('swag-bundle.detail.errorTitle'),
                        message: exception
                    });
                });
        }
    }
});
```

## Providing the public directory

Changes to the administration are loaded by our Webpack configuration. For this to work properly, a `public` directory is created in the `<plugin root>/Resources` directory when building
the administration via the command `./psh.phar administration:build`. It contains a minified file containing your administration JS code.
This directory has to be part of your plugin when using it on live systems, since its content will then be copied into the project's `public` directory.
Only the project's public directory is accessible for a website, so your code has to be there as well.

Now you still have to make sure this file gets loaded in the administration.

For this purpose, create a new file like this: `<plugin root>/src/Resources/views/administration/index.html.twig`.
The file's contents should look like this:
```twig
{% sw_extends 'administration/index.html.twig' %}

{% block administration_scripts %}
    {{ parent() }}
    <script type="text/javascript" src="{{ asset('bundles/bundleexample/administration/js/bundle-example.js') }}"></script>
{% endblock %}
```

You're extending from the original `administration/index.html.twig` template and you override the block `administration_scripts`, which contains the
scripts for the administration.
In there, you simply add your plugin's minified version of the javascript code from the project's public directory.

**And with that, your Administration implementation of the Bundle module is fully done and working! Go ahead and try it out and create a new bundle,
assign some products and a discount to it.**

Now, that you can manage your bundles in the Administration, start working on the Storefront to display your bundles.
This is done in [next step](./080-storefront.md).
