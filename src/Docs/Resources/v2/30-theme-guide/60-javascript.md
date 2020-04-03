[titleEn]: <>(JavaScript)
[hash]: <>(article:theme_javascript)

You can add JavaScript to theme to interact with the DOM, make some API calls or change the behavior of the storefront.

By default, Shopware 6 look inside the `<plugin root>/src/Resources/app/storefront/src` folder of your plugin to load a `main.js` file.

You can simple put your own JavaScript code in here. Shopware 6 support the [ECMAScript 6](http://www.ecma-international.org/ecma-262/6.0/) and will transpile your code to ES5 for legacy browser support.

The recommended way to add JavaScript is to write a JavaScript Plugin for the storefront.

## Writing a JavaScript plugin

Storefront JavaScript plugins are vanilla JavaScript ES6 classes that extend from our Plugin base class.
For more background information on JavaScript classes, take a look [here](https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Classes).
To get started create a `src/Resources/app/storefront/src/example-plugin` folder and put an `example-plugin.plugin.js` file in there.
Inside that file create and export a ExamplePlugin class that extends the base Plugin class:

```js
// src/Resources/app/storefront/src/example-plugin/example-plugin.plugin.js

import Plugin from 'src/plugin-system/plugin.class';

export default class ExamplePlugin extends Plugin {
}
```

Each Plugin has to implement the `init()` method. This method will be called when your plugin gets initialized and is the entrypoint to your custom logic.
In your case you add an callback to the onScroll event from the window and check if the user has scrolled to the bottom of the page. If so we display an alert.
Your full plugin now looks like this:

```js
// src/Resources/app/storefront/src/example-plugin/example-plugin.plugin.js

import Plugin from 'src/plugin-system/plugin.class';

export default class ExamplePlugin extends Plugin {
    init() {
        window.onscroll = function() {
            if ((window.innerHeight + window.pageYOffset) >= document.body.offsetHeight) {
                alert('seems like there\'s nothing more to see here.');
            }
        };
    }
}
```

## Registering your plugin

Next you have to tell Shopware that your plugin should be loaded and executed. Therefore you have to register your plugin in the PluginManager.
Open up `main.js` file inside your `src/Resources/app/storefront/src` folder and add the following example code to register your plugin in teh PluginManager.

```js
// src/Resources/app/storefront/src/main.js

// import all necessary storefront plugins
import ExamplePlugin from './example-plugin/example-plugin.plugin';

// register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('ExamplePlugin', ExamplePlugin);
```

You also can bind your plugin to an DOM element by providing an css selector:

```js
// src/Resources/app/storefront/src/main.js

// import all necessary storefront plugins
import ExamplePlugin from './example-plugin/example-plugin.plugin';
 
// register them via the existing PluginManager
const PluginManager = window.PluginManager;
PluginManager.register('ExamplePlugin', ExamplePlugin, '[data-example-plugin]');
```

In this case the plugin just gets executed if the HTML document contains at least one element with the `data-scroll-detector` attribute.
You could use `this.el` inside your plugin to access the DOM element your plugin is bound to.

## Loading your plugin

You bound your plugin to the css selector "[data-example-plugin]" so you have to add DOM elements with this attribute on the pages you want your plugin to be active.
Therefore create an `Resources/views/storefront/page/content/` folder and create an `index.html.twig` template.
Inside this template extend from the `@Storefront/storefront/page/content/index.html.twig` and overwrite the `base_main_inner` block.
After the parent content of the blog add an template tag that has the `data-example-plugin` attribute.
For more information on how template extension works, take a look [here](./30-twig-templates.md).

```twig
{% sw_extends '@Storefront/storefront/page/content/index.html.twig' %}

{% block base_main_inner %}
    {{ parent() }}

    <template data-example-plugin></template>
{% endblock %}
```

With this template extension your plugin is active on every content page, like the homepage or category listing pages.

## Configuring your plugins

You can configure your plugins from inside the templates via data-options.
Define a static `options` object inside your plugin and assign your options with default values to it.
In your case define a text option and as a default value use the text you previously directly prompted to the user.
And instead of the hard coded string inside the `alert()` use your new option value.

```js
// src/Resources/app/storefront/src/main.js

import Plugin from 'src/plugin-system/plugin.class';

export default class ExamplePlugin extends Plugin {
    static options = {
        /**
         * Specifies the text that is prompted to the user
         * @type string
         */
        text: 'seems like there\'s nothing more to see here.',
    };

    init() {
        const that = this;
        window.onscroll = function() {
            if ((window.innerHeight + window.pageYOffset) >= document.body.offsetHeight) {
                alert(that.options.text);
            }
        };
    }
}
```

Now you are able to override the text that is prompted to the user from inside your templates.
Therefore create an `product-detail` folder inside your `Resources/views/storefront/page` folder and add an `index.html.twig` file inside that folder.
In your template extend from `@Storefront/storefront/page/product-detail/index.html.twig` and override the `page_product_detail_content`.
After the parent content add an template tag with the `data-example-plugin` tag also to activate your plugin on product detail pages.
Next add an `data-{your-plugin-name-in-kebab-case}-options` (`data-example-plugin-options`) attribute to the DOM element you registered your plugin on (the template tag).
As the value of this attribute use the options you want to override as a json object.

```twig
{% sw_extends '@Storefront/storefront/page/product-detail/index.html.twig' %}

{% set examplePluginOptions = {
    text: "Are you not interested in this product?"
} %}

{% block page_product_detail_content %}
    {{ parent() }}

    <template data-example-plugin data-example-plugin-options='{{ examplePluginOptions|json_encode }}'></template>
{% endblock %}
```

It is best practice to use a variable for the options because this is extendable from plugins.

Please have a look at the [Storefront JavaScript](./../90-refernces-internals/90-storefront-javascript.md) documentation
for more information about how to interact with the PluginManager existing plugins in more detail and.
